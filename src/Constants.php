<?php

namespace Drupal\drupalauth4ssp;

/**
 * Contains constants related to the DrupalAuth for SimpleSAMLphp module.
 * @static
 */
class Constants {

  /**
   * Empty private constructor to stop people from instantiating this class.
   */
  private function __construct() {
  }

  /**
   * SSO login path (including leading '/').
   * @var string
   */
  public const SSO_LOGIN_PATH = '/ssoLogin';

}
