<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\SsoUserValidator;

use Drupal\drupalauth4ssp\UserValidatorInterface;
use Drupal\user\UserInterface;

/**
 * Represents a validator that doesn't allow a root account to be SSO-enabled.
 */
class NoRootAccountSsoUserValidator implements UserValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function isUserValid(UserInterface $user) : bool {
    return ($user->id() !== 1) ? TRUE : FALSE;
  }

}
