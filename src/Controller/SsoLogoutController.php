<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\ReturnToUrlManager;
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
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack;
   */
  protected $requestStack;

  /**
   * Helper service to obtain and determine if 'ReturnTo' URL can be used.
   *
   * @var \Drupal\drupalauth4ssp\Helper\ReturnToUrlManager;
   */
  protected $returnToUrlManager;

  /**
   * Creates this controller object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\Helper\ReturnToUrlManager $returnToUrlManager
   *   Helper service to obtain and determine if 'ReturnTo' URL can be used.
   */
  public function __construct(AccountInterface $account, $requestStack, $returnToUrlManager) {
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->returnToUrlManager = $returnToUrlManager;
  }
  /**
   * Contains controller logic.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Response redirecting user to page indicated in query string return URL,
   *   or, if that URL doesn't exist, to the home page.
   */
  public function handle() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$this->account->isAnonymous()) {
      //Log the user out.
      user_logout();
    }
    // Attempt to redirect, if possible (if the return URL parameter isn't
    // empty and is allowed) to the return URL query string parameter.
    // Otherwise, redirect to the home page.
    if ($this->returnToUrlManager->isReturnUrlValid()) {
      return new RedirectResponse($this->returnToUrlManager->getReturnUrl());
    }
    else {
      return new RedirectResponse(Url::fromRoute('<front>')->setAbsolute()->toString());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_user'), $container->get('request_stack'), $container->get('drupalauth4ssp.return_url_manager'));
  }

}
