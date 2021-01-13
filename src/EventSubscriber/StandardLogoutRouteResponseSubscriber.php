<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use SimpleSAML\Session;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles standard logout route (user.logout) responses.
 *
 * To ensure behavior consistent with NormalLogoutLoginRouteRequestSubscriber,
 * we ensure the user is appropriately redirected (to the home page) after a
 * successful logout. In addition, we destroy the simpleSAMLphp session and
 * initiate single logout, if the user was logged in as an SSO-enabled user.
 */
class StandardLogoutRouteResponseSubscriber implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Kill switch with which to disable caching.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $cacheKillSwitch;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Creates a standard logout route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cacheKillSwitch
   *   Kill switch with which to disable caching.
   */
  public function __construct(AccountInterface $account, $requestStack, $cacheKillSwitch) {
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->cacheKillSwitch = $cacheKillSwitch;
  }

  /**
   * Handles response event for standard logout routes.
   *
   * @todo Change deprecated use of FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   *
   * @throws \Exception
   *   Thrown if something went wrong when attempting to obtain simpleSAMLphp
   *   session information.
   */
  public function handleStandardLogoutResponse($event) : void {
    $request = $event->getRequest();
    $masterRequest = $this->requestStack->getMasterRequest();

    // If we're not using the default logout route, get out.
    if ($request->attributes->get('_route') !== 'user.logout') {
      return;
    }

    // We don't want any caching.
    $this->cacheKillSwitch->trigger();

    // If this response was generated because of an exception, we don't want to
    // mess with things; get out.
    if ($request->attributes->get('exception')) {
      return;
    }
    // If we haven't actually logged out, get out.
    if (!$this->account->isAnonymous()) {
      return;
    }

    // See if our handler of hook_user_logout set a flag indicating we should
    // proceed. We only want to execute this handler as part of the normal
    // logout process associated with the user.logout route -- so we check to
    // ensure this variable is set properly as a sanity check (if this variable
    // were not set properly, hook_user_logout would not have been invoked
    // properly, so something is abnormal about the logout process).
    $wasLogoutHookInvoked = &drupal_static('drupalauth4ssp_var_wasLogoutHookInvoked');
    if (empty($wasLogoutHookInvoked)) {
      return;
    }
    // Now, before initiating single logout, we must determine if the user who
    // logged out was an SSO-enabled user. hook_user_logout() sets a flag
    // indicating if this was the case, so we check it here.
    $wasLoggedOutUserSsoEnabledUser = &drupal_static('drupalauth4ssp_var_wasLoggedOutUserSsoEnabledUser');
    if (empty($wasLoggedOutUserSsoEnabledUser)) {
      return;
    }

    $homePageUrl = Url::fromRoute('<front>')->setAbsolute()->toString();

    if ($wasLoggedOutUserSsoEnabledUser) {
      // Here, we will destroy the SSP session and initiate single logout.
      // Kill the SSP session by expiring it.
      $sspSession = Session::getSessionFromRequest();
      if ($session) {
        foreach ($session->getAuthorities() as $authority) {
          $session->setAuthorityExpire($authority, 1);
        }
      }

      // Build the single logout URL -- head back to the home page when we're
      // done.
      $singleLogoutUrl = UrlHelpers::generateSloUrl($masterRequest->getHost(), $homePageUrl);
      // Redirect to the single logout URL.
      $event->setResponse(new RedirectResponse($singleLogoutUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
    }
    else {
      // Just redirect to the home page.
      $event->setResponse(new RedirectResponse($homePageUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleStandardLogoutResponse']];
  }

}
