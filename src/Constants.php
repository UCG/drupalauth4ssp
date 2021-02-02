<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

/**
 * Contains constants related to the DrupalAuth for SimpleSAMLphp module.
 *
 * @static
 */
final class Constants {

  /**
   * Empty private constructor to stop people from instantiating this class.
   */
  private function __construct() {
  }

  /**
   * Query string key for query string parameter indicating simpleSAMLphp state.
   *
   * @var string
   */
  public const SSP_STATE_QUERY_STRING_KEY = 'sspState';

  /**
   * Identifier for the simpleSAMLphp stage corresponding to an auth request.
   */
  public const SSP_LOGIN_SSP_STAGE_ID = 'drupalauth:authentication';

}
