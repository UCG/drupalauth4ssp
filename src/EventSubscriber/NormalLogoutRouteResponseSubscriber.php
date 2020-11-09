<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use Drupal\drupalauth4ssp\SimpleSamlPhpLink;
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
   * URL helper service.
   *
   * @var \Drupal\drupalauth4ssp\Helper\UrlHelperService
   */
  protected $urlHelper;

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
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService $urlHelper
   *   URL helper service.
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink $sspLink
   *   Service to interact with simpleSAMLphp.
   */
  public function __construct(AccountInterface $account, $urlHelper, $sspLink) {
    $this->account = $account;
    $this->urlHelper = $urlHelper;
    $this->sspLink = $sspLink;
  }

  /**
   * Handles response event for standard logout routes.
   *
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   * @return void
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  public function handleNormalLogoutResponse($event) : void {
    if (!$event->isMasterRequest()) {
      return;
    }
    $request = $event->getRequest();

    // If we're not using the default logout route, get out.
    if ($request->attributes->get('_route') != 'user.logout') {
      return;
    }
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
    // Proceed only if authenticated.
    if (!$this->sspLink->isAuthenticated()) {
      return;
    }

    // If we can, we'll redirect to the referrer. This overrides the
    // default user.logout behavior.
    $referrer = $request->server->get('HTTP_REFERER');
    // Check that the referrer is valid and points to a local URL.
    if ($this->urlHelper->isUrlValidAndLocal($referrer)) {
      $returnUrl = $referrer;
    }
    else {
      // Otherwise, just go to the front page.
      $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
    }

    // Now go ahead and initiate single logout.
    // Build the single logout URL.
    $singleLogoutUrl = UrlHelpers::generateSloUrl($request->getHost(), $returnUrl);
    // Redirect to the single logout URL
    $event->setResponse(new RedirectResponse($singleLogoutUrl));
    $event->stopPropagation();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleNormalLogoutResponse']];
  }

}
