<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures (un)authenticated users are redirected approp. on logout (login).
 *
 * This is necessary for logout routes because some browsers sometimes issue
 * multiple nearly concurrent GET requests for a resource. In order to ensure we
 * support such browsers cleanly, we redirect authenticated users appropriately
 * on logout routes, instead of issuing a 403 (the default behavior). This is to
 * ensure that, for instance, if multiple requests are issued for a logout, the
 * user isn't shown a 403, since, for one of those requests, the user had
 * already been logged out for one of the other requests. We perform this for
 * login routes to obtain behavior similar to that on the service providers.
 */
class NormalLogoutLoginRouteRequestSubscriber implements EventSubscriberInterface {

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
   * URL helper service.
   *
   * @var \Drupal\drupalauth4ssp\Helper\UrlHelperService
   */
  protected $urlHelper;

  /**
   * Creates a new normal logout/login route request subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService
   *   URL helper service.
   */
  public function __construct(AccountInterface $account, $requestStack, $urlHelper) {
    $this->account = $account;
    $this->urlHelper = $urlHelper;
    $this->requestStack = $requestStack;
  }

  /**
   * Handles request event for logout and login routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   * @return void
   */
  public function handleLogoutOrLoginRequest($event) : void {

    // If we're not an unauthenticated user on the logout route, or an
    // authenticated user on the login route, get out, as this subscriber is
    // only designed for those cases.
    $route = $request->attributes->get('_route');
    $userIsAnonymous = $account->isAnonymous();
    if (!(($route == 'user.login' && !$userIsAnonymous) || ($route == 'user.logout' && $userIsAnonymous))) {
      return;
    }

    $masterRequest = $this->requestStack->getMasterRequest();

    // We will attempt to redirect to the referrer; if it's invalid, we redirect
    // to the home page.
    $referrer = $masterRequest->server->get('HTTP_REFERER');
    // Check valididity of referrer URL, and that it is local.
    if ($this->urlHelper->isUrlValidAndLocal($referrer)) {
      $returnUrl = $referrer;
    }
    else {
      $returnUrl = Url::fromRoute('<front>', [], ['absolute' => TRUE]);
    }

    $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['handleLogoutOrLoginRequest']];
  }

}
