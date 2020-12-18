<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures single-logout is initiated (if applicable) for non-SSO logout routes.
 *
 * Normally, logging out through the default route ('user.logout') will not
 * initiate single logout. Hence, we subscribe to the kernel.response event in
 * order to ensure all of this done when the user navigates to user.logout,
 * provided there is an SSP session.
 */
class NormalLogoutRouteResponseSubscriber implements EventSubscriberInterface {

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Service to interact with simpleSAMLphp.
   *
   * @var \Drupal\drupalauth4ssp\SimpleSamlPhpLink
   */
  protected $sspLink;

  /**
   * Creates a normal logout route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink $sspLink
   *   Service to interact with simpleSAMLphp.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cacheKillSwitch
   *   Kill switch with which to disable caching.
   */
  public function __construct(AccountInterface $account, $requestStack, $sspLink, $cacheKillSwitch) {
    $this->account = $account;
    $this->sspLink = $sspLink;
    $this->cacheKillSwitch = $cacheKillSwitch;
    $this->requestStack = $requestStack;
  }

  /**
   * Handles response event for standard logout routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   * @return void
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  public function handleNormalLogoutResponse($event) : void {
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
    // proceed.
    $shouldInitiateLogout = &drupal_static('drupalauth4ssp_var_shouldInitiateSspLogout');
    if (!isset($shouldInitiateLogout) || !$shouldInitiateLogout) {
      return;
    }

    // Redirect to the home page.
    $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
    
    // Redirect immediately if we are unauthenticated with simpleSAMLphp.
    if (!$this->sspLink->isAuthenticated()) {
      $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
    }
    else {
      // Otherwise, go ahead and initiate single logout. First, destroy the
      // curent simpleSAMLphp session.
      $this->sspLink->invalidateSession();
      // Then, destroy the user ID cookie.
      drupalauth4ssp_unset_user_cookie();
      // Build the single logout URL.
      $singleLogoutUrl = UrlHelpers::generateSloUrl($masterRequest->getHost(), $returnUrl);
      // Redirect to the single logout URL
      $event->setResponse(new RedirectResponse($singleLogoutUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleNormalLogoutResponse']];
  }

}
