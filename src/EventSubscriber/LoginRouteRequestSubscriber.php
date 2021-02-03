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
 * Ensures authenticated users are redirected appropriately on login.
 *
 * Some browsers sometimes issue multiple GET requests for a resource. In order
 * to ensure we support such browsers cleanly, we redirect authenticated users
 * appropriately on user.login routes, in the same way we would redirect
 * unauthenticated users, respectively. This is to ensure that, if multiple
 * login requests are issued at nearly the same time, the user receives
 * the same behavior as when only one request is issued. We do this because,
 * taking as an example two requests issued for the user.login route at nearly
 * the same time, one of these requests might be issued by an authenticated
 * user, because one request might log the user in before the other request is
 * initiated. Similar logic applies to user.logout routes, and we implement such
 * logic in @see \Drupal\drupalauth4ssp\Controller\UserLogoutController. This
 * subscriber also ensures that the appropriate simpleSAMLphp method is called
 * if this route was navigated to by a simpleSAMLphp authentication source.
 */
class LoginRouteRequestSubscriber implements EventSubscriberInterface {

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
   * The simpleSAMLphp integration manager.
   *
   * @var \Drupal\drupalauth4ssp\SspIntegrationManager
   */
  protected $sspIntegrationManager;

  /**
   * Creates a new login route request subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\SspIntegrationManager $sspIntegrationManager
   *   The simpleSAMLphp integration manager.
   */
  public function __construct(AccountInterface $account, $requestStack, $sspIntegrationManager) {
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->sspIntegrationManager = $sspIntegrationManager;
  }

  /**
   * Handles request event for login routes.
   *
   * Note: This method never returns, except in the case of exceptions, if user
   * parameters are passed back to simpleSAMLphp.
   *
   * @todo Change deprecated use of GetResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   *
   * @throws \Exception
   *   Thrown if the simpleSAMLphp stage given by
   *   \Drupal\drupalauth4ssp\Constants::SSP_LOGIN_SSP_STAGE_ID is invalid, and
   *   SSP does not have a suitable redirect URL.
   * @throws \SimpleSAML\Error\NoState
   *   Thrown if simpleSAMLphp state can't be found, and SSP does not have a
   *   suitable redirect URL.
   */
  public function handleLoginRequest($event) : void {
    // If we're not an authenticated user on the login route, get out, as this
    // subscriber is only designed for that case.
    $route = $event->getRequest()->attributes->get('_route');
    if ($route !== 'user.login' || $this->account->isAnonymous()) {
      return;
    }

    // If appropriate, complete simpleSAMLphp authentication (this will only
    // occur if their is an appropriate simpleSAMLphp state ID and the user is
    // is an SSO-enabled user). This call won't return if the simpleSAMLphp
    // authentication is completed, except in the case of exceptions.
    $this->sspIntegrationManager->completeSspAuthenticationForLoginRouteIfAppropriate();

    // Otherwise, redirect to the front page.
    $masterRequest = $this->requestStack->getMasterRequest();
    $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
    $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['handleLoginRequest']];
  }

}
