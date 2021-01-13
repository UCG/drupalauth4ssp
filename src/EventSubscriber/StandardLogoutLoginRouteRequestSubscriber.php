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
 * Some browsers sometimes issue multiple GET requests for a resource. In order
 * to ensure we support such browsers cleanly, we redirect unauthenticated users
 * appropriately on user.logout routes, and authenticated users appropriately on
 * user.login routes, in the same way we would redirect authenticated and
 * unauthenticated users, respectively. This is to ensure that, if multiple
 * logout/login requests are issued at nearly the same time, the user receives
 * the same behavior as when only one request is issued. We do this because,
 * taking as an example two requests issued for the user.logout route at nearly
 * the same time, one of these requests might be issued by an unauthenticated
 * user, because one request might log the user out before the other request is
 * initiated.
 */
class StandardLogoutLoginRouteRequestSubscriber implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Creates a new standard logout/login route request subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, $requestStack) {
    $this->account = $account;
    $this->requestStack = $requestStack;
  }

  /**
   * Handles request event for logout and login routes.
   *
   * @todo Change deprecated use of GetResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   */
  public function handleStandardLogoutOrLoginRequest($event) : void {
    // If we're not an unauthenticated user on the logout route, or an
    // authenticated user on the login route, get out, as this subscriber is
    // only designed for those cases.
    $route = $event->getRequest()->attributes->get('_route');
    $userIsAnonymous = $this->account->isAnonymous();
    if (!(($route === 'user.login' && !$userIsAnonymous) || ($route === 'user.logout' && $userIsAnonymous))) {
      return;
    }

    $masterRequest = $this->requestStack->getMasterRequest();

    // We will redirect to the home page.
    $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
    $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['handleStandardLogoutOrLoginRequest']];
  }

}
