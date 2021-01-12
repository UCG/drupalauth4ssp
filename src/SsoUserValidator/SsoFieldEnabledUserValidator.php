<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\SsoUserValidator;

use Drupal\drupalauth4ssp\UserValidatorInterface;
use Drupal\user\UserInterface;

/**
 * Ensures only users with the SSO-enabled field set to 'TRUE' are allowed.
 */
class SsoFieldEnabledSsoUserValidator implements UserValidatorInterface {

  /**
   * {@inheritdoc}
   */
  public function isUserValid(UserInterface $user) : bool {
    // Reject if SSO-enabled user field not 'TRUE'.
    return (bool) $user->get('field_sso_enabled_user')->value;
  }

}
