<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\user\UserInterface;

/**
 * Represents an interface for user validators.
 *
 * User validators are used to check that a user account satisfies some
 * constraint.
 */
interface UserValidatorInterface {

  /**
   * Checks to see if this user satisfies a constraint.
   *
   * @param \Drupal\user\UserInterface $user
   *   User entity to check.
   *
   * @return bool
   *   'TRUE' if user satisfies constraint; else 'FALSE'.
   */
  public function isUserValid(UserInterface $user) : bool;

}
