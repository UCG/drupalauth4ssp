<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

/**
 * Contains helpers related to URLs (generation, etc.).
 * @static
 */
final class UrlHelpers {

  /**
   * Private constructor to ensure no one can instantiate this static class.
   */
  private function __construct() {
  }

  /**
   * Generates a single logout URL with the given 'ReturnTo' URL.
   *
   * @param string $returnToUrl
   *   URL to return to after logout.
   * @param string $hostname
   *   Host name to use.
   * @return string
   *   Single logout URL
   * @throws \InvalidArgumentException
   *   $returnToUrl or $hostname is empty().
   */
  public static function generateSloUrl(string $hostname, string $returnToUrl) {
    if (empty($returnToUrl)) {
      throw new \InvalidArgumentException('$returnToUrl is empty.');
    }
    if (empty($hostname)) {
      throw new \InvalidArgumentException('$hostname is empty.');
    }

    $queryString = http_build_query(['ReturnTo' => $returnToUrl]);
    return 'https://' . $hostname . '/simplesaml/saml2/idp/SingleLogoutService.php?' . $queryString;
  }

}
