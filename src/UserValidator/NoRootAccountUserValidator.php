<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\UserValidator;

use Drupal\drupalauth4ssp\UserValidatorInterface;
use Drupal\user\UserInterface;

/**
 * Represents a validator that doesn't allow root accounts.
 */
class NoRootAccountUserValidator implements UserValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function isUserValid(UserInterface $user) : bool {
    return ($user->id() !== 1) ? TRUE : FALSE;
  }

}
