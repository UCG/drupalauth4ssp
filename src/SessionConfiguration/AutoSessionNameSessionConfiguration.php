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
   * If we are not running from simpleSAMLphp, uses the base class's getName()
   * method. Otherwise, looks for a session cookie in $request starting with
   * "SESS" or "SSESS", and returns it if it exists. If more than one such
   * session cookie exists, this method will throw an exception. If no such
   * cookie exists, this method will use an appropriate prefix ("SESS" for
   * unsecure requests; "SSESS" for secure requests) and the base class's
   * getUnprefixedName() method.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the set of request cookies appears to contain more than one
   *   session cookie with a valid name.
   */
  protected function getName(Request $request) {
    // Check to see if we are coming from simpleSAMLphp.
    global $isDrupalRunningFromSimpleSamlPhp;
    if (!$isDrupalRunningFromSimpleSamlPhp) {
      return parent::getName($request);
    }

    $existingCookieName = $this->getExistingCookieName($request);
    if ($existingCookieName === NULL) {
      return ($request->isSecure() ? "SSESS" : "SESS") . parent::getUnprefixedName($request);
    }
    else {
      assert(is_string($existingCookieName));
      return $existingCookieName;
    }
  }

  /**
   * Gets the name of the existing session cookie already set on $request.
   *
   * If no session cookie appears to be set on $request, returns 'NULL'.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return string|null
   *   The cookie name, if a session cookie could be found, else 'NULL'.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the set of request cookies appears to contain more than one
   *   session cookie with a valid name.
   */
  protected function getExistingCookieName($request) : ?string {
    // Look through all the cookies in $request to find one with the correct
    // name.
    $sessionCookieName = NULL;
    foreach ($request->cookies->all() as $cookieName => $cookie) {
      // See if the cookie starts with "SESS" or "SSESS".
      if ((mb_substr($cookieName, 0, 4) === 'SESS') || (mb_substr($cookieName, 0, 5) === 'SSESS')) {
        if ($validCookieFound) {
          // If we already found a valid cookie, blow up.
          throw new InvalidOperationException('More than one session cookie appears to be set on $request.');
        }
        $sessionCookieName = $cookieName;
      }
    }

    return $sessionCookieName;
  }

  /**
   * Gets the unprefixed session name.
   *
   * If we are not running from simpleSAMLphp, uses the base class's getName()
   * method. Otherwise, looks for a session cookie in $request starting with
   * "SESS" or "SSESS", and uses the unprefixed part of it, if it exists. If
   * more than one such session cookie exists, this method will throw an
   * exception. If no such cookie exists, this method will use the base class's
   * getUnprefixedName() method.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\InvalidOperationException
   *   Thrown if the set of request cookies appears to contain more than one
   *   session cookie with a valid name.
   */
  protected function getUnprefixedName(Request $request) {
    // Check to see if we are coming from simpleSAMLphp.
    global $isDrupalRunningFromSimpleSamlPhp;
    if (!$isDrupalRunningFromSimpleSamlPhp) {
      return parent::getUnprefixedName($request);
    }

    $existingCookieName = $this->getExistingCookieName($request);
    if ($existingCookieName === NULL) {
      return parent::getUnprefixedName($request);
    }
    else {
      assert(is_string($existingCookieName));
      if (mb_substr($existingCookieName, 0, 4) === 'SESS') {
        return $mb_substr($existingCookieName, 4);
      }
      else {
        return mb_substr($existingCookieName, 5);
      }
    }
    
  }

}
