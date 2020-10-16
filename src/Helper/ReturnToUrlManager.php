<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Helper;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\PathMatcherInterface;

/**
 * Manages stuff related to the 'ReturnTo' query string parameter.
 *
 * Ensures the 'ReturnTo' query string parameter is not in the "forbidden URLs"
 * list, and that it is non-empty. Also retrieves this parameter if so asked.
 */
class ReturnToUrlManager {

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack;
   */
  protected $requestStack;

  /**
   * DrupalAuth for SimpleSamlPHP configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

  /**
   * Path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Creates a new \Drupal\drupalauth4ssp\Helper\ReturnToUrlManager object.
   *
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   Path mtcher
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack
   */
  public function __construct(PathMatcherInterface $pathMatcher, ConfigFactoryInterface $configurationFactory, $requestStack) {
    $this->pathMatcher = $pathMatcher;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->requestStack = $requestStack;
  }

  /**
   * Checks to see if the return URL query string parameter is valid.
   *
   * The return URL query string parameter is valid if it 1) is non-empty and 2)
   * is allowed by the drupalauth4ssp module settings.
   *
   * @return bool 'TRUE' if valid, else 'FALSE'
   */
  public function isReturnUrlValid() : bool {
    // This is adapted from the non-forked version of
    // drupalauth4ssp_user_login_submit() in drupalauth4ssp.module.
    $returnToUrl = $this->requestStack->getCurrentRequest()->query->get('ReturnTo');
    // Reject if return to URL is empty.
    if (empty($returnToUrl)) {
      return FALSE;
    }
    // Check if return to URL is allowed.
    $returnToAllowedList = $this->configuration->get('returnto_list');
    return $this->pathMatcher->matchPath($returnToUrl, $returnToAllowedList);
  }

  /**
   * Gets the 'ReturnTo' URL parameter.
   *
   * @return string|NULL 'ReturnTo' URL parameter; possibly NULL or empty if no
   *   such parameter
   */
  public function getReturnUrl() : ?string {
    return $this->requestStack->getCurrentRequest()->query->get('ReturnTo');
  }

}
