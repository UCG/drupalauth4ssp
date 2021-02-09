<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles the response event for login routes.
 *
 * This subscriber ensures that, after a login process initiated by a service
 * provider is completed, the appropriate set of user attributes is passed back
 * to simpleSAMLphp.
 */
class LoginRouteResponseSubscriber implements EventSubscriberInterface {

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
   * Creates a normal login route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
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
   * Handles response event for login route.
   *
   * Notes: If control is passed to simpleSAMLphp, this method never returns,
   * except when there is an exception.
   *
   * @todo Switch from using deprecated FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function handleLoginResponse($event) : void {
    // Don't do anything on subrequests.
    if (!$event->isMasterRequest()) {
      return;
    }

    $request = $event->getRequest();

    // If we're not on the login route, get out.
    if ($request->attributes->get('_route') !== 'user.login') {
      return;
    }
    // If we're not logged in, get out.
    if ($this->account->isAnonymous()) {
      return;
    }
    // If this response was generated because of an exception, we don't
    // typically want to mess with things. The one exception to this rule is the
    // \Drupal\Core\Form\EnforcedResponseException exception -- this exception
    // is used for flow control by Drupal's form system, rather than for error
    // handling, so it is okay to ignore it.
    $exception = $request->attributes->get('exception');
    if ($exception && !($exception instanceof EnforcedResponseException)) {
      return;
    }

    // If appropriate, complete simpleSAMLphp authentication (this will only
    // occur if their is an appropriate simpleSAMLphp state ID and the user is
    // an SSO-enabled user).
    $this->sspIntegrationManager->completeSspAuthenticationForLoginRouteIfAppropriate();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleLoginResponse']];
  }

}
