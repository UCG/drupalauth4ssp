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
    if (static::$isPossibleIdpSessionCookieDomain !== NULL) {
      return static::$isPossibleIdpSessionCookieDomain;
    }

    // Otherwise, load up the SiteInformation class, from which we can retrieve
    // information about the current environment, domain, etc.
    // @todo Determine if using DRUPAL_ROOT is safe.
    require_once(DRUPAL_ROOT . '/../General/SiteInformation.php');

    // Determine the cookie domain, depending on the current environment.
    $environmentType = SiteInformation::getEnvironmentType();
    switch ($environmentType) {
      case SiteInformation::DDEV_ENVIRONMENT:
        static::$isPossibleIdpSessionCookieDomain = '.ddev.site';
        break;
      default:
        // For now, just use full domain name if not using DDev.
        static::$isPossibleIdpSessionCookieDomain = SiteInformation::getFullDomainName();
        break;
    }

    assert(static::$isPossibleIdpSessionCookieDomain !== NULL);
    return static::$isPossibleIdpSessionCookieDomain;
  }

  /**
   * Gets the "is possible IdP session" cookie expiration timestamp.
   *
   * @return int
   *   Unix timestamp corresponding to expiration time.
   */
  public static function getIsPossibleIdpSessionCookieExpiration() : int {
    // Make the expiry time as large as possible to stay on the safe side (users
    // on a service provider may not be automatically logged if this cookie is
    // unset when it should be set, but the converse is not true).
    return PHP_INT_MAX;
  }

  /**
   * Sets the "is possible IdP session" cookie using setcookie().
   */
  public static function setIsPossibleIdpSessionCookie() : void {
    // Grab the cookie's name.
    static::$isPossibleIdpSessionCookieName = \Drupal::configFactory()->get('drupalauth4ssp.settings')->get('is_possible_idp_session_cookie_name');

    setcookie(static::$isPossibleIdpSessionCookieName, 'TRUE', static::getIsPossibleIdpSessionCookieExpiration(), '/', static::getIsPossibleIdpSessionCookieDomain());
  }

  /**
   * Clears the "is possible IdP session" cookie using setcookie().
   */
  public static function clearIsPossibleIdpSessionCookie() : void {
    // Grab the cookie's name.
    static::$isPossibleIdpSessionCookieName = \Drupal::configFactory()->get('drupalauth4ssp.settings')->get('is_possible_idp_session_cookie_name');

    setcookie(static::$isPossibleIdpSessionCookieName, '', time() - 3600, '/', static::getIsPossibleIdpSessionCookieDomain());
  }

}
