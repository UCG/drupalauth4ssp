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
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack;
   */
  protected $requestStack;

  /**
   * Creates a new \Drupal\drupalauth4ssp\Helper\UrlHelperService object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param PathValidator $pathValidator
   *   Path validator.
   */
  public function __construct($requestStack, $pathValidator) {
    $this->pathValidator = $pathValidator;
    $this->requestStack = $requestStack;
  }

  /**
   * Gets the 'ReturnTo' URL query parameter.
   *
   * @return string|NULL 'ReturnTo' URL parameter; possibly NULL or empty if no
   *   such parameter
   */
  public function getReturnToUrl() : ?string {
    return $this->requestStack->getMasterRequest()->query->get('ReturnTo');
  }

  /**
   * Checks to see if the 'ReturnTo' URL query string parameter is valid.
   *
   * The return URL query string parameter is valid if it 1) is non-empty() and
   * 2) is allowed by the drupalauth4ssp module settings.
   *
   * @return bool
   *   'TRUE' if valid, else 'FALSE'.
   */
  public function isReturnToUrlValid() : bool {
    // This is adapted from the non-forked version of
    // drupalauth4ssp_user_login_submit() in drupalauth4ssp.module.
    $returnToUrl = $this->requestStack->getMasterRequest()->query->get('ReturnTo');
    // Return 'TRUE' if 'ReturnTo' URL is valid and local.
    return $this->isUrlValidAndLocal($returnToUrl);
  }

  /**
   * Checks if a URL is both valid and local.
   * 
   * Checks to see if URL path $url is valid and points to the local Drupal
   * installation.
   *
   * @param mixed $url
   *   URL to check.
   * @return bool
   *   'TRUE' if conditions in description are met, else 'FALSE'
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
