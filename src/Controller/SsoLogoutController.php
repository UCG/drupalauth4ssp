<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;

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
   * Creates this controller object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, $requestStack) {
    $this->account = $account;
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
    $request = $requestStack->getCurrentRequest();
    if (!$account->isAnonymous())
      //Log the user out.
      user_logout();
    }
    // Redirect, if possible (if the return URL parameter exists and is local
    // and valid) to the return URL query string parameter. Otherwise, redirect
    // to the home page.
    $request = $request
  }

}
