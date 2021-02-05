<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\UserAttributeProcessor;

use Drupal\drupalauth4ssp\UserAttributeProcessorInterface;
use Drupal\user\UserInterface;

/**
 * Represents an attribute processor for returning the user's roles.
 */
class UserRolesProcessor implements UserAttributeProcessorInterface {

  /**
   * Creates a new user roles attribute processor object.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeName() : string {
    return 'roles';
  }

  /**
   * Returns the roles attribute of $user.
   *
   * Returns the roles of $user in a form that can be added to the simpleSAMLphp
   * $state array for returning to the SP. Does not return locked roles
   * (authenticated/anonymous).
   *
   * @param UserInterface $user
   *   User whose "roles" attribute should be returned.
   *
   * @return array
   *   An array, each of whose elements is a role ID.
   */
  public function getAttribute(UserInterface $user) : array {
    return $user->getRoles(TRUE);
  }

}
