<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpAttributeException;
use Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpInternalConfigException;
use Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpNoLibraryException;
use Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpLockTimeoutException;
use Drupal\simplesamlphp_auth\Helper\StringHelpers;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Error\CriticalConfigurationError;

/**
 * Service to interact with the simpleSAMLphp authentication library.
 *
 * All information related to the simpleSAMLphp session is cached after being
 * accessed once. This prevents "race" conditions where the simpleSAMLphp
 * session expires during the request.
 */
class SimpleSamlPhpLink {

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $moduleConfiguration;

  /**
   * simpleSAMLphp object.
   *
   * @var \SimpleSAML\Auth\Simple
   */
  protected $simpleSaml;

  /**
   * SAML attributes for user logged into simpleSAMLphp.
   *
   * @var array
   */
  protected $attributes;

  /** 'TRUE' if there was a valid simpleSAMLphp session when property set.
   *
   * @var bool
   */
  protected $isLoggedIn;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Synchronization lock service to synchronize access to SSP sessions.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * Names of locks that may have been acquired.
   *
   * If a lock has been acquired, its name will be in this list. This list also
   * may contain a lock name corresponding to a lock that has not yet been
   * acquired.
   *
   * @var array
   */
  private $lockNames;

  /**
   * Creates a new \Drupal\simplesamlphp_auth\SimpleSamlPhpLink instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   Synchronization lock.
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpNoLibraryException
   *   Thrown if the simpleSAMLphp library cannot be located
   */
  public function __construct(ConfigFactoryInterface $configFactory, LockBackendInterface $lock, $requestStack) {
    $this->moduleConfiguration = $configFactory->get('simplesamlphp_auth.settings');
    $this->requestStack = $requestStack;
    $this->lock = $lock;
    $this->lockNames = [];
    // Ensure the simpleSAMLphp library is loaded.
    static::acquireSimpleSamlLibrary();
  }

  /**
   * Destructs this object.
   */
  public function __destruct() {
    // Release all locks
    $this->releaseAllAcquiredLocks();
  }

  /**
   * Forwards the user to the IdP for authentication.
   *
   * Notes: This method breaks the Symfony request-response flow.
   * Postconditions: This method throws an exception or fails to return.
   *
   * @return void
   * @param string $returnUrl
   *   Url to return to after successful login.
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if the simpleSAMLphp session storage type could not be determined
   *   to be something other than 'phpsession'
   */
  public function performExternalAuthentication(string $returnUrl) : void {
    // Ensure simpleSAMLphp Simple instance is set up.
    $this->createSimpleSamlInstanceIfNecessary();
    // Check session storage type
    $this->checkSimpleSamlPhpStorageTypeValid();

    // Go ahead and forward to the IdP for login.
    $this->simpleSaml->login(['ReturnTo' => $returnUrl]);

    // We shouldn't get this far
    assert(false);
  }

  /**
   * Forwards the user to the IdP for passive authentication.
   *
   * This method merely instructs simpleSAMLphp to ask the IdP if a valid
   * "global" session exists on the IdP. If such a session exists, the user
   * is redirected back to the current request URL. If no such session exists,
   * or another error occurs during authentication, the user is redirected
   * to $errorUrl. This method does not return unless an exception occurs before
   * a redirect.
   * Notes: This method breaks the Symfony request-response flow.
   * Postconditions: This method throws an exception or fails to return.
   *
   * @param string|NULL $errorUrl URL to redirect to if authentication fails, or
   *   NULL or an empty string to not provide such a URL
   * @return void
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if the simpleSAMLphp session storage type could not be determined
   *   to be something other than 'phpsession'
   */
  public function performPassiveExternalAuthentication(?string $errorUrl) : void {
    // Ensure simpleSAMLphp Simple instance is set up.
    $this->createSimpleSamlInstanceIfNecessary();
    // Check session storage type
    $this->checkSimpleSamlPhpStorageTypeValid();

    // Use the current absolute request URL as the return URL.
    $returnUrl = $this->requestStack->getCurrentRequest()->getUri();

    // Go ahead and attempt a passive login; don't provide the error URL if we
    // don't have it.
    if (StringHelpers::isUnsetOrEmpty($errorUrl)) {
      $this->simpleSaml->login([
        'ReturnTo' => $returnUrl,
        'isPassive' => TRUE,
      ]);
    }
    else {
      $this->simpleSaml->login([
        'ReturnTo' => $returnUrl,
        'isPassive' => TRUE,
        'ErrorURL' => $errorUrl,
      ]);
    }

    // We shouldn't get this far.
    assert(false);
  }

  /**
   * Check whether user is authenticated by the IdP.
   *
   * @return bool
   *   If the user is authenticated by the IdP.
   */
  public function isSsoServiceExternallyAuthenticated() : bool {
    // Ensure simpleSAMLphp Simple instance is set up.
    $this->createSimpleSamlInstanceIfNecessary();

    return $this->simpleSaml->isAuthenticated();
  }

  /**
   * Gets the unique id of the user from the IdP.
   *
   * @return string
   *   The authname, if it exists.
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpAttributeException
   *   Exception when authname attribute isn't set.
   */
  public function getExternalAuthname() : string {
    return $this->getIdpAttribute($this->moduleConfiguration->get('unique_id'));
  }

  /**
   * Gets the name attribute.
   *
   * @return string
   *   The name attribute.
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpAttributeException
   *   Exception when username attribute isn't set.
   */
  public function getExternalUsername() : string {
    return $this->getIdpAttribute($this->moduleConfiguration->get('user_name'));
  }

  /**
   * Gets the mail attribute.
   *
   * @return string
   *   The mail attribute.
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpAttributeException
   *   Exception when mail attribute isn't set.
   */
  public function getExternalUserEmail() : string {
    return $this->getIdpAttribute($this->moduleConfiguration->get('mail_attr'));
  }

  /**
   * Gets all SimpleSAML attributes.
   *
   * @return array
   *   Array of SimpleSAML attributes.
   */
  public function getIdpAttributes() {
    // Ensure simpleSAMLphp Simple instance is set up.
    $this->createSimpleSamlInstanceIfNecessary();

    return $this->attributes;
  }

  /**
   * Get a specific SimpleSAML attribute.
   *
   * @param string $attribute
   *   The name of the attribute.
   *
   * @return mixed|bool
   *   The attribute value or FALSE.
   *
   * @throws \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpAttributeException
   *   Exception when attribute is not set.
   */
  public function getIdpAttribute($attribute) {
    $attributes = $this->getIdpAttributes();

    if (isset($attributes)) {
      if (!empty($attributes[$attribute][0])) {
        return $attributes[$attribute][0];
      }
    }

    throw new SimpleSamlPhpAttributeException(sprintf('Error in simplesamlphp_auth.module: no valid "%s" attribute set.', $attribute));
  }

  /**
   * Log a user out through the SimpleSAMLphp instance.
   *
   * @param string|NULL $redirect_path
   *   The path to redirect to after logout.
   */
  public function externalLogout(?string $redirectPath = NULL) {
    // Ensure simpleSAMLphp Simple instance is set up.
    $this->createSimpleSamlInstanceIfNecessary();

    if (!$redirectPath) {
      $redirectPath = base_path();
    }

    $this->simpleSaml->logout($redirectPath);
  }

    /**
   * Instantiates the simpleSAMLphp Simple instance if unset.
   *
   * Postconditions: $this->simpleSaml is properly instantiated, or an
   * exception is thrown.
   *
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpNoLibraryException
   *   Thrown if the simpleSAMLphp library can't be located
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  protected function createSimpleSamlInstanceIfNecessary() : void {
    // Don't need to do anything if instance has already been created.
    if (isset($this->simpleSaml))
      return;

    // Get configured authentication source
    $authenticationSource = $this->moduleConfiguration->get('auth_source');

    // First, grab the SSP session cookie name, which (to prevent race
    // conditions) we will lock on to prevent concurrent access to the SSP
    // session (that is, as long as all calls to simpleSAMLphp are performed
    // through this service). We also lock on the "auth token" cookie name, in
    // case user data shared across requests can be accessed through that cookie
    // alone. If we have neither cookie, we shouldn't need to worry about
    // locking, as we shouldn't be able to access any user data that is shared
    // between requests without a suitable identification cookie.

    // Grab simpleSAMLphp configuration so we can get SSP cookie names.
    $simpleSamlPhpConfiguration = static::getSimpleSamlConfiguration();
    try {
      // Get cookie names.
      $sspSessionCookieName = $simpleSamlPhpConfiguration->getValue('session.cookie.name');
      $sspAuthTokenCookieName = $simpleSamlPhpConfiguration->getValue('session.authtoken.cookiename');
      // Check that we have a non-null, non-empty session cookie.
      if ($this->requestStack->cookies->has($sspSessionCookieName)) {
        $sspSessionCookieValue = $this->requestStack->cookies->get($sspSessionCookieName);
        if (!StringHelpers::isUnsetOrEmpty($sspSessionCookieValue)) {
          // Go ahead and lock on the cookie value (120 second timeout). First
          // place the lock name in the 'lockNames' property, to ensure it is
          // properly released if something goes wrong.
          $this->lockNames[] = $sspSessionCookieValue;
          if (!$this->lock->acquire($sspSessionCookieValue, 120)) {
            // Failed to acquire lock. Remove lock name from array above.
            unset($this->lockNames[$sspSessionCookieValue]);
            throw new SimpleSamlPhpLockTimeoutException();
          }
        }
      }
      // Check that we have a non-null, non-empty "auth token" cookie.
      if ($this->requestStack->cookies->has($sspAuthTokenCookieName)) {
        $sspAuthTokenCookieValue = $this->requestStack->cookies->get($sspAuthTokenCookieName);
        if (!StringHelpers::isUnsetOrEmpty($sspAuthTokenCookieValue)) {
          // Go ahead and lock on the cookie value (120 second timeout). First
          // place the lock name in the 'lockNames' property, to ensure it is
          // properly released if something goes wrong.
          $this->lockNames[] = $sspAuthTokenCookieValue;
          if (!$this->lock->acquire($sspAuthTokenCookieValue, 120)) {
            // Failed to acquire lock. Remove lock name from array above.
            unset($this->lockNames[$sspAuthTokenCookieValue]);
            throw new SimpleSamlPhpLockTimeoutException();
          }
        }
      }
      try {
        $this->simpleSaml = new Simple($authenticationSource);
        // Force load of all attributes if logged in.
        $wasAuthenticated = $this->simpleSaml->isAuthenticated();
        if ($wasAuthenticated) {
          $this->attributes = $this->simpleSaml->getAttributes();
        }
        if ($wasAuthenticated && !$this->simpleSaml->isAuthenticated()) {
          // If the session has expired since acquiring attributes, clear the
          // attributes we acquired.
          $this->attributes = [];
          $this->isLoggedIn = FALSE;
        }
        else {
          $this->isLoggedIn = $wasAuthenticated;
        }
      }
      catch (CriticalConfigurationError $e) {
        // Since this is an "expected" exception (e.g., relating to user
        // mis-configuration, etc.), we wrap it in our own exception type for user
        // friendliness.
        throw new SimpleSamlPhpInternalConfigException(NULL, 0, $e);
      }
    }
    finally {
      if (!isset($this->simpleSaml)) {
        // We failed to create the simpleSAMLphp instance. We should release all
        // the locks we might have set.
        $this->releaseAllAcquiredLocks();
      }
    }
    
    // Verify postcondition.
    assert(isset($this->simpleSaml));
  }

  /**
   * Checks to ensure the simpleSAMLphp session storage type is valid (i.e., not
   * set to 'phpsession'). Throws an exception if the session storage type could
   * not be determined to be valid.
   *
   * @return void
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpInternalConfigException
   */
  protected function checkSimpleSamlPhpStorageTypeValid() : void {
    // Grab simpleSAMLphp configuration if we can.
    $simpleSamlPhpConfiguration = static::getSimpleSamlConfiguration();

    // Grab session storage value, as configured.
    $simpleSamlPhpSessionStorage = $simpleSamlPhpConfiguration->getValue('store.type');
    // Check to ensure we aren't using PHP sessions.
    if($simpleSamlPhpSessionStorage == 'phpsession') {
      throw new SimpleSamlPhpInternalConfigException("simpleSAMLphp session storage type is set to 'phpsession'.");
    }
  }

  /**
   * Ensures all locks that have been acquired by this object are released.
   *
   * @return void
   */
  protected function releaseAllAcquiredLocks() {
    foreach ($this->lockNames as $lockName) {
      if (isset($lockName)) {
        $this->lock->release($lockName);
      }
    }
  }

  /**
   * Attempts to load simpleSAMLphp library if library location specified.
   *
   * Also checks to ensure the library is loaded one way or another.
   * Preconditions: None.
   * Postconditions: This method only returns if the simpleSAMLphp library
   * is loaded.
   *
   * @throws
   *   \Drupal\simplesamlphp_auth\Exception\SimpleSamlPhpNoLibraryException
   *   Thrown if the simpleSAMLphp library can't be located
   */
  protected static function acquireSimpleSamlLibrary() {
    // Load simpleSAMLphp from the appropriate directory if possible.
    if ($dir = Settings::get('simplesamlphp_dir')) {
      include_once $dir . '/lib/_autoload.php';
    }

    // Ensure simpleSAMLphp is loaded.
    if (!class_exists('SimpleSAML\Configuration')) {
      throw new SimpleSamlPhpNoLibraryException();
    }
  }

  /**
   * Attempts to obtain the current simpleSAMLphp configuration.
   *
   * @return Configuration SSP configuration.
   * @throws \Drupal\simplesamlphp_auth\SimpleSamlPhpInternalConfigException
   *   Configuration couldn't be loaded.
   */
  protected static function getSimpleSamlConfiguration() : Configuration {
    try {
      return Configuration::getInstance();
    }
    catch (CriticalConfigurationError $e) {
      throw new SimpleSamlPhpInternalConfigException('Could not obtain simpleSAMLphp configuration.', 0, $e);
    }
  }
}
