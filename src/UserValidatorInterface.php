<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\user\UserInterface;

/**
 * Represents an interface for user validators.
 *
 * User validators are used to ensure a user account is SSO-enabled.
 */
interface UserValidatorInterface {

  /**
   * Checks to see if this user is SSO-enabled.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity to check.
   *
   * @return bool
   *   'TRUE' if user is SSO-enabled; else 'FALSE'.
   */
  public function isUserValid(UserInterface $user) : bool;

}
