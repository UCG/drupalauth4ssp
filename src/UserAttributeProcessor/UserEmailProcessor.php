<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\UserAttributeProcessor;

use Drupal\drupalauth4ssp\UserAttributeProcessorInterface;
use Drupal\user\UserInterface;

/**
 * Represents an attribute processor for returning the user email.
 */
class UserEmailProcessor implements UserAttributeProcessorInterface {

  /**
   * Creates a new user email attribute processor object.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function getAttributeName() : string {
    return 'mail';
  }

  /**
   * Returns the email attribute of $user.
   *
   * Returns the attribute of $user in a form that can be added to the
   * simpleSAMLphp $state array for returning to the SP.
   *
   * @param UserInterface $user
   *   User whose email attribute should be returned.
   *
   * @return array
   *   An array with a single element (the user's email address).
   */
  public function getAttribute(UserInterface $user) : array {
    return [$user->getEmail()];
  }

}
