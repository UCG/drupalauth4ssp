<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for handling logouts initiated by simpleSAMLphp.
 */
class SsoLogoutController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Helper service to obtain and determine if 'ReturnTo' URL can be used.
   *
   * @var \Drupal\drupalauth4ssp\Helper\UrlHelperService;
   */
  protected $urlHelper;

  /**
   * Creates this controller object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService $urlHelper
   *   Helper service to obtain and determine if 'ReturnTo' URL can be used.
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, $urlHelper, $requestStack) {
    $this->account = $account;
    $this->urlHelper = $urlHelper;
    $this->requestStack = $requestStack;
  }
  /**
   * Contains controller logic.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Response redirecting user to page indicated in query string return URL,
   *   or, if that URL doesn't exist, to the home page.
   */
  public function handle() {
    if (!$this->account->isAnonymous()) {
      //Log the user out.
      user_logout();
    }
    // Clear the drupalauth4ssp cookie.
    drupalauth4ssp_unset_user_cookie();
    // Attempt to redirect, if possible (if the return URL parameter isn't
    // empty and is allowed) to the return URL query string parameter.
    // Otherwise, redirect to the home page.
    $masterRequest = $this->requestStack->getMasterRequest();
    if ($this->urlHelper->isReturnToUrlValid()) {
      return new RedirectResponse($this->urlHelper->getReturnToUrl(), HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod()));
    }
    else {
      return new RedirectResponse(Url::fromRoute('<front>')->setAbsolute()->toString(), HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_user'), $container->get('drupalauth4ssp.url_helper'), $container->get('request_stack'));
  }

}
