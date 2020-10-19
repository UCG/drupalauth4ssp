<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Session\AccountInterface;

/**
 * Represents an interface for account validators.
 *
 * Account validators are used to ensure a user account is SSO-enabled.
 */
interface AccountValidatorInterface {

  /**
   * Checks to see if this account is SSO-enabled.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check.
   * @return bool 'TRUE' if account is SSO-enabled; else 'FALSE'
   */
  public function isAccountValid(AccountInterface $account) : bool;

}
