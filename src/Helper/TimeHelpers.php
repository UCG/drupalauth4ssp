<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

/**
 * Helper methods for working with time.
 * @static
 */
final class TimeHelpers {

  /**
   * Empty private constructor to stop people from instantiating this class.
   */
  private function __construct() {
  }

  /**
   * Returns the current Unix timestamp.
   *
   * Uses the request time, if it is available -- otherwise uses time().
   *
   * @return int
   *   Current Unix timestamp.
   * @throws \RuntimeException
   *   Thrown if the PHP integer size is not at least four bytes (this is done
   *   to ensure PHP integers are large enough to store the current Unix
   *   timestamp).
   */
  public static function getCurrentTime() : int {
    if (PHP_INT_SIZE < 4) {
      throw new \RuntimeException("Size of PHP integers must be at least four bytes.");
    }

    // Use the request time, if available -- otherwise use the current time.
    if (empty($_SERVER['REQUEST_TIME']) || !is_numeric($_SERVER['REQUEST_TIME'])) {
      return (int) time();
    }
    else {
      return (int) $_SERVER['REQUEST_TIME'];
    }
  }

}
