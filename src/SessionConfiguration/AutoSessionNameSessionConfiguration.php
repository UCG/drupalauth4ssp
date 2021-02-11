<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\SessionConfiguration;

use Drupal\Core\Session\SessionConfiguration;
use Drupal\drupalauth4ssp\Exception\InvalidOperationException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A session config object that automatically determines the session name.
 *
 * The session name is determined by looking for a matching session cookie in
 * the current requests set of cookies.
 */
class AutoSessionNameSessionConfiguration extends SessionConfiguration {

  /**
   * Gets the session name.
   *
   * Looks for a session cookie in $request starting with "SESS" or "SSESS", and
   * returns it if it exists. If more than one such session cookie exists, or if
   * no such cookie exists, this method will throw an exception.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the set of request cookies does not appear to contain exactly
   *   one session cookie with a valid name.
   */
  protected function getName(Request $request) {
    // Look through all the cookies in $request to find one with the correct
    // name.
    $validCookieFound = FALSE;
    foreach ($request->cookies->all() as $cookieName => $cookie) {
      // See if the cookie starts with "SESS" or "SSESS".
      if ((mb_substr($cookieName, 0, 4) === 'SESS') || (mb_substr($cookieName, 0, 5) === 'SSESS')) {
        if ($validCookieFound) {
          // If we already found a valid cookie, blow up.
          throw new InvalidOperationException('More than one session cookie appears to be set on $request.');
        }
        $sessionCookieName = $cookieName;
        $validCookieFound = TRUE;
      }
    }

    return $sessionCookieName;
  }

  /**
   * Gets the unprefixed session name.
   *
   * Looks for a session cookie in $request starting with "SESS" or "SSESS", and
   * returns it if it exists. If more than one such session cookie exists, or if
   * no such cookie exists, this method will throw an exception.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the set of request cookies does not appear to contain exactly
   *   one session cookie with a valid name.
   */
  protected function getUnprefixedName(Request $request) {
    $fullName = getName($request);
    if (mb_substr($cookieName, 0, 4) === 'SESS') {
      return $mb_substr($cookieName, 4);
    }
    else {
      return mb_substr($cookieName, 5);
    }
  }

}
