<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\drupalauth4ssp\Exception\SimpleSamlPhpAttributeException;
use Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Configuration;
use SimpleSAML\Error\CriticalConfigurationError;

/**
 * Service to interact with the simpleSAMLphp authentication library.
 *
 * All information related to the simpleSAMLphp session is cached after being
 * accessed once. This prevents "race" conditions where the simpleSAMLphp
 * session expires during the request, which could make the methods in this
 * class return undefined values if nothing was cached.
 */
class SimpleSamlPhpLink {

  /**
   * SAML attributes for user logged into simpleSAMLphp.
   *
   * @var array
   */
  protected $attributes = [];

  /** 'TRUE' if there was a valid simpleSAMLphp session when property set.
   *
   * @var bool
   */
  protected $isLoggedIn;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * simpleSAMLphp object.
   *
   * @var \SimpleSAML\Auth\Simple
   */
  protected $simpleSaml;

  /**
   * Creates a new \Drupal\simplesamlphp_auth\SimpleSamlPhpLink instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   The configuration factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $configurationFactory, $requestStack) {
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->requestStack = $requestStack;
  }

  /**
   * Initiates simpleSAMLphp authentication (unless already logged in).
   *
   * Notes: This method breaks the Symfony request-response flow.
   * Postconditions: This method fails to return unless 1) simpleSAMLphp already
   * has an authenticated session, or 2) it throws an exception.
   *
   * @return void
   * @param string $returnUrl
   *   URL to return to after successful login.
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if the simpleSAMLphp session storage type could not be determined
   *   to be something other than 'phpsession'
   */
  public function initiateAuthenticationIfNecessary(string $returnUrl) : void {
    $this->prepareSimpleSamlStuff();
    // Check session storage type
    $this->checkSimpleSamlPhpStorageTypeValid();

    // Go ahead and forward to the IdP for login.
    $this->simpleSaml->requireAuth(['ReturnTo' => $returnUrl, 'KeepPost' => 'FALSE']);
  }

  /**
   * Checks whether the user has an authenticated simpleSAMLphp session.
   *
   * @return bool
   *   'TRUE' if the user has an authenticated SSP session; else 'FALSE'.
   */
  public function isAuthenticated() : bool {
    $this->prepareSimpleSamlStuff();

    return $this->isLoggedIn;
  }

  /**
   * Gets all simpleSAMLphp attributes.
   *
   * @return array
   *   Array of attributes.
   */
  public function getAttributes() {
    $this->prepareSimpleSamlStuff();

    return $this->attributes;
  }

  /**
   * Get a specific simpleSAMLphp attribute.
   *
   * @param string $attribute
   *   The name of the attribute to retrieve.
   *
   * @return mixed
   *   The attribute value.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpAttributeException
   *   Exception when attribute is not set.
   */
  public function getAttribute($attribute) {
    $this->getAttributes();

    if (isset($this->attributes)) {
      if (!empty($this->attributes[$attribute][0])) {
        return $this->attributes[$attribute][0];
      }
    }

    throw new SimpleSamlPhpAttributeException(sprintf('Error in drupalauth4ssp module: no valid "%s" attribute set.', $attribute));
  }

  /**
   * Prepares SSP "Simple" instance and caches SSP session info, if necessary.
   *
   * This should be called by all methods in this class before performing
   * anything related to simpleSAMLphp. This method ensures the simpleSAMLphp
   * method is properly set up, and that all needed data associated with
   * simpleSAMLphp is loaded into this object's properties.
   *
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  protected function prepareSimpleSamlStuff() : void {
    // Don't need to do anything if instance has already been created.
    if (isset($this->simpleSaml))
      return;

    // Get configured authentication source
    $authenticationSource = $this->configuration->get('authsource');

    try {
      // Attempt to create SSP "Simple" instance.
      $this->simpleSaml = new Simple($authenticationSource);
      // Force load of all attributes if logged in.
      $wasAuthenticated = $this->simpleSaml->isAuthenticated();
      if ($wasAuthenticated) {
        $this->attributes = $this->simpleSaml->getAttributes();
      }
      if ($wasAuthenticated && !$this->simpleSaml->isAuthenticated()) {
        // If the session has expired since acquiring attributes, clear the
        // attributes we acquired and set a flag indicated the SSP session is no
        // more. We don't want to associate attributes with a session that has
        // closed.
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
