<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

use Drupal\Component\Utility\Unicode;

/**
 * Helper methods for working with the HTTP protocol (setting headers, etc.).
 * @static
 */
final class HttpHelpers {

  /**
   * Empty private constructor to stop people from instantiating this class.
   */
  private function __construct() {
  }

  /**
   * Determines the redirect code to use for a temporary/see other redirect.
   *
   * Although a 302 response code is often used for most non-permanent
   * redirects, it is often appropriate to use a 303 redirect if the request
   * method is something other than GET. This is because it is technically
   * valid (though rare in practice), according to the HTTP spec, for browsers
   * to issue the redirection after a request according to the method used in
   * the original request (e.g., POST). This is often undesirable, and a 303
   * redirect can be used in these circumstances to force the browser to convert
   * the request type to GET. This may not work with some very old browsers.
   *
   * @param string $requestMethod
   *   Request method.
   * @return int
   *   Redirect code (302 or 303).
   */
  public static function getAppropriateTemporaryRedirect(string $requestMethod) : int {
    if (Unicode::strcasecmp($requestMethod, 'GET') === 0) {
      // Use 302 redirect for GET requests.
      return 302;
    }
    else {
      return 303;
    }
  }

}
