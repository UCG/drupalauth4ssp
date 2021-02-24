<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\user\UserInterface;

/**
 * Puts a user attribute in a form that can be consumed by simpleSAMLphp.
 */
interface UserAttributeProcessorInterface {

  /**
   * Returns an attribute of $user.
   *
   * Returns an attribute of $user in a form that can be added to the
   * simpleSAMLphp $state array for returning to the SP.
   *
   * @param UserInterface $user
   *   User whose attribute should be returned.
   *
   * @return array
   *   An array of all sub-values associated with the attribute. For instance,
   *   if user roles are being returned from this method, this value might
   *   consist of an array of all the roles' IDs. If a user name is being
   *   returned, this value should consist of an array with a single element
   *   (the user name).
   */
  public function getAttribute(UserInterface $user) : array;

  /**
   * Gets the attribute name associated with this processor.
   *
   * @return string
   *   Attribute name.
   */
  public function getAttributeName() : string;

}
