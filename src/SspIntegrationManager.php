<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\CookieHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;

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
   * @param \Drupal\drupalauth4ssp\EntityCache $userEntityCache
   *   Entity cache to retrieve user entities.
   * 
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, $userEntityCache) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->$userEntityCache = $userEntityCache;
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
   * @throws \Drupal\drupalauth4ssp\InvalidOperationException
   *   Thrown if the user is not logged in, or is not an SSO-enabled user.
   */
  public function updateSspStateWithUserAttributes(array &$state) : void {

  }

  /**
   * Tells whether the current user is authenticated and SSO-enabled.
   *
   * @return bool
   *   Returns 'TRUE' if the current user is both authenticated and SSO-enabled,
   *   else returns 'FALSE'.
   */
  protected function isCurrentUserAuthenticatedAndSsoEnabled() : bool {
    return !$this->account->isAnonymous() && $this->userValidator->isUserValid($this->userEntityCache->get($this->account->id()));
  }

}
