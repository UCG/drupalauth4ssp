<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

use General\SiteInformation;

/**
 * Helper methods for working with cookies.
 *
 * @static
 */
final class CookieHelpers {

  /**
   * Domain of the "is possible IdP session" cookie, or NULL if domain not set.
   *
   * @var string|null
   */
  private static $isPossibleIdpSessionCookieDomain = NULL;

  /**
   * Empty private constructor to stop people from instantiating this class.
   */
  private function __construct() {
  }

  /**
   * Determines and returns the domain for the "is possible IdP session" cookie.
   *
   * @return string
   *   Cookie name.
   */
  public static function getIsPossibleIdpSessionCookieDomain() : string {
    // Returned cached value if set.
    if ($isPossibleIdpSessionCookieDomain !== NULL) {
      return $isPossibleIdpSessionCookieDomain;
    }

    // Otherwise, load up the SiteInformation class, from which we can retrieve
    // information about the current environment, domain, etc.
    require_once(DRUPAL_ROOT . '/../General/SiteInformation.php');

    // Determine the cookie domain, depending on the current environment.
    $environmentType = SiteInformation::getEnvironmentType();
    switch ($environmentType) {
      case SiteInformation::DDEV_ENVIRONMENT:
        $isPossibleIdpSessionCookieDomain = '.ddev.site';
        break;
      default:
        // For now, just use full domain name if not using DDev.
        $isPossibleIdpSessionCookieDomain = SiteInformation::getFullDomainName();
        break;
    }

    assert($isPossibleIdpSessionCookieDomain !== NULL);
    return $isPossibleIdpSessionCookieDomain;
  }

}
