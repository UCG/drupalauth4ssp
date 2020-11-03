<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

use Drupal\Component\Utility\UrlHelper;

/**
 * URL-related helper service.
 */
class UrlHelperService {
  
  /**
   * Path validator.
   *
   * @var \Drupal\Core\Path\PathValidator
   */
  protected $pathValidator;

  /**
   * Creates a new UrlHelperService object.
   *
   * @param PathValidator $pathValidator Path validator.
   */
  public function __construct($pathValidator) {
    $this->pathValidator = $pathValidator;
  }
  
  /**
   * Checks if a URL is both valid and local.
   * 
   * Checks to see if URL path $url is valid, accessible by the current user,
   * and if it points to the local Drupal installation.
   *
   * @return bool 'TRUE' if conditions in description are met, else 'FALSE'
   * @param mixed $url URL to check
   */
  public function isUrlValidAndLocal($url) {
    if (!$url) {
      return FALSE;
    }
    // This is the validation procedure defined in
    // src/Controller/SimpleSamlPhpAuthLoginController.php of the
    // simplesamlphp_auth module (the actual contrib module, not our forked
    // version).
    global $base_url;
    try {
      return $this->pathValidator->isValid($url) && UrlHelper::externalIsLocal($url, $base_url);
    }
    catch (\InvalidArgumentException $ex) {
      // Swallow this exception, as we know then that $url isn't valid.
      return FALSE;
    }
  }
}
