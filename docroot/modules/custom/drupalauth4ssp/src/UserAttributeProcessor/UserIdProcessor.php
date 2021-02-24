<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\UserAttributeProcessor;

use Drupal\drupalauth4ssp\UserAttributeProcessorInterface;
use Drupal\user\UserInterface;

/**
 * Represents an attribute processor for returning the user's UID.
 */
class UserIdProcessor implements UserAttributeProcessorInterface {

  /**
   * Creates a new user ID attribute processor object.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeName() : string {
    return 'uid';
  }

  /**
   * Returns the ID attribute of $user.
   *
   * Returns the attribute of $user in a form that can be added to the
   * simpleSAMLphp $state array for returning to the SP.
   *
   * @param UserInterface $user
   *   User whose UID attribute should be returned.
   *
   * @return array
   *   An array with a single element (the user's UID).
   */
  public function getAttribute(UserInterface $user) : array {
    return [$user->id()];
  }

  /**
   * Attempts to pull the user ID from the set of simpleSAMLphp attributes.
   *
   * This method is essentially the reverse of getAttribute().
   *
   * @param array $attributes
   *   Array of simpleSAMLphp attributes.
   *
   * @return int
   *   User ID.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $attributes does not contain a valid user ID attribute.
   */
  public function getUid(array $attributes) : int {
    if (!array_key_exists($this->getAttributeName(), $attributes)) {
      throw new \InvalidArgumentException('User ID does not exist in $attributes.');
    }

    $userId = $attributes[$this->getAttributeName()];
    if (!is_int($userId) || $userId < 0) {
      throw new \InvalidArgumentException('User ID in $attributes is invalid.');
    }

    return $userId;
  }

}
