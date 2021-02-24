<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Constants;
use Drupal\drupalauth4ssp\Exception\InvalidOperationException;
use Drupal\drupalauth4ssp\Helper\CookieHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use SimpleSAML\Auth\Source;
use SimpleSAML\Auth\State;

/**
 * Manages integration with simpleSAMLphp.
 */
class SspIntegrationManager {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * User attribute collector used to obtain attributes for simpleSAMLphp.
   *
   * @var \Drupal\drupalauth4ssp\UserAttributeCollector;
   */
  protected $userAttributeCollector;

  /**
   * Entity cache to retrieve user entities.
   *
   * @var \Drupal\drupalauth4ssp\EntityCache
   */
  protected $userEntityCache;

  /**
   * Validator used to ensure user is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\UserValidatorInterface
   */
  protected $userValidator;

  /**
   * Creates a new \Drupal\drupalauth4ssp\SspIntegrationManager instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The current route match service.
   * @param \Drupal\drupalauth4ssp\UserAttributeCollector $userAttributeCollector
   *   User attribute collector used to obtain attributes for simpleSAMLphp.
   * @param \Drupal\drupalauth4ssp\EntityCache $userEntityCache
   *   Entity cache to retrieve user entities.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, RouteMatchInterface $currentRouteMatch, $userAttributeCollector, $userEntityCache, $requestStack) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->currentRouteMatch = $currentRouteMatch;
    $this->userAttributeCollector = $userAttributeCollector;
    $this->userEntityCache = $userEntityCache;
    $this->requestStack = $requestStack;
  }

  /**
   * If appropriate, updates SSP state array and completes SSP authentication.
   *
   * This method should can be called on the user.login route to try to finish
   * an SSP authentication process which may have been initiated in an SSP
   * authentication source. This method only updates the simpleSAMLphp state
   * with the current user's attributes and calls
   * \SimpleSAML\Auth\Source::completeAuth() under the following conditions: 1)
   * the current user is authenticated, 2) the current user is SSO-enabled, and
   * 3) the query string contains a simpleSAMLphp state ID parameter. Otherwise,
   * this method returns without doing anything.
   *
   * Notes: If this method calls \SimpleSAML\Auth\Source::completeAuth(), it
   * will not return unless there are exceptions.
   *
   * @throws \Exception
   *   Thrown if the simpleSAMLphp stage given by
   *   \Drupal\drupalauth4ssp\Constants::SSP_LOGIN_STAGE_ID is invalid, and
   *   SSP does not have a suitable redirect URL.
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if 'user.login' is not the current route.
   * @throws \SimpleSAML\Error\NoState
   *   Thrown if simpleSAMLphp state can't be found, and SSP does not have a
   *   suitable redirect URL.
   */
  public function completeSspAuthenticationForLoginRouteIfAppropriate() : void {
    if ($this->currentRouteMatch->getRouteName() !== 'user.login') {
      throw new InvalidOperationException('The current route is not the user.login route.');
    }

    $currentRequest = $this->requestStack->getCurrentRequest();

    // If we have a query string parameter giving us a simpleSAMLphp state ID,
    // we will assume that this request has been initiated by a simpleSAMLphp
    // authentication source, and will return the appropriate parameters to
    // simpleSAMLphp.
    if ($currentRequest->query->has(Constants::SSP_STATE_QUERY_STRING_KEY)) {
      // Only proceed if user is logged in and SSO-enabled.
      if ($this->isCurrentUserAuthenticatedAndSsoEnabled()) {
        // Recreate the simpleSAMLphp state, assemble the data we will be
        // passing back, and call \SimpleSAML\Auth\Source::completeAuth() with
        // the updated state.
        $sspState = State::loadState((string) $currentRequest->query->get(Constants::SSP_STATE_QUERY_STRING_KEY), Constants::SSP_LOGIN_STAGE_ID);
        $this->updateSspStateWithUserAttributesInternal($sspState);

        // Set the "is possible IdP session" cookie before proceeding.
        CookieHelpers::setIsPossibleIdpSessionCookie();

        // Pass control to simpleSAMLphp to complete the auth process.
        Source::completeAuth($sspState);
        // The previous call should never return.
        assert(FALSE);
      }
      
    }
  }

  /**
   * Tells whether the current user is authenticated and SSO-enabled.
   *
   * @return bool
   *   Returns 'TRUE' if the current user is both authenticated and SSO-enabled,
   *   else returns 'FALSE'.
   */
  public function isCurrentUserAuthenticatedAndSsoEnabled() : bool {
    return (!$this->account->isAnonymous() && $this->userValidator->isUserValid($this->userEntityCache->get($this->account->id()))) ? TRUE : FALSE;
  }

  /**
   * Performs a Drupal logout if necessary.
   *
   * This method is to be called from a simpleSAMLphp authentication source.
   */
  public function performLogoutFromSspAuthSource() : void {
    // Log an SSO-enabled user out.
    if ($this->isCurrentUserAuthenticatedAndSsoEnabled()) {
      user_logout();

      // Clear the "is session at IdP" cookie.
      CookieHelpers::clearIsPossibleIdpSessionCookie();
    }
  }

  /**
   * Updates the simpleSAMLphp $state array with the attributes of current user.
   *
   * @param array $state
   *   The simpleSAMLphp state array.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the user is not logged in, or is not an SSO-enabled user.
   */
  public function updateSspStateWithUserAttributes(array &$state) : void {
    if (!$this->isCurrentUserAuthenticatedAndSsoEnabled()) {
      throw new InvalidOperationException('The user is not logged in or is not SSO-enabled.');
    }

    $this->updateSspStateWithUserAttributesInternal($state);
  }

  /**
   * Updates the simpleSAMLphp $state array with the attributes of current user.
   *
   * This is different from
   * SspIntegrationManager::updateSspStateWithUserAttributes() in that it does
   * not check first to ensure the user is logged in and is SSO-enabled.
   *
   * @param array $state
   *   The simpleSAMLphp state array.
   */
  protected function updateSspStateWithUserAttributesInternal(array &$state) : void {
    // Fill the state array.
    foreach ($this->userAttributeCollector->getAttributes($user) as $attributeName => $attribute) {
      $state['Attributes'][$attributeName] = $attribute;
    }
  }

}
