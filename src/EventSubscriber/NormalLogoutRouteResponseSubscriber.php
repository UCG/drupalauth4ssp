<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use SimpleSAML\Auth\Simple;
use SimpleSAML\Session;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures single-logout is initiated (if applicable) for non-SSO logout routes.
 *
 * Normally, logging out through the default route ('user.logout') will not
 * destroy the simpleSAMLphp session, nor initiate single logout, nor destroy
 * the drupalauth4ssp cookie. Hence, we subscribe to the RESPONSE event in order
 * to ensure all of this done when the user navigates to user.logout, provided
 * there is an SSP session.
 */
class NormalLogoutRouteResponseSubscriber implements EventSubscriberInterface {

  /**
   * DrupalAuth for SimpleSamlPHP configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

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
   * Creates a normal logout route response subscriber instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService
   *   URL helper service.
   */
  public function __construct(AccountInterface $account, ConfigFactoryInterface $configurationFactory, $urlHelper) {
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->account = $account;
    $this->urlHelper = $urlHelper;
  }

  /**
   * Handles response event for standard logout routes.
   *
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   * @return void
   * @throws \SimpleSAML\Error\CriticalConfigurationError
   *   Thrown if something was wrong with the simpleSAMLphp configuration.
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

    // Try to create the simpleSAMLphp instance.
    $simpleSaml = new Simple($this->configuration->get('authsource'));
    // Proceed only if authenticated.
    if (!$simpleSaml->isAuthenticated()) {
      return;
    }

    // Proceed. If we can, we'll redirect to the referrer. This overrides the
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

    // Destroy session and initiate single logout.
    // Taken from drupalauth4ssp.module in the non-forked version.
    // Invalidate SimpleSAML session by expiring it.
    $session = Session::getSessionFromRequest();
    // Backward compatibility with SimpleSAMP older than 1.14.
    // SimpleSAML_Session::getAuthority() has been removed in 1.14.
    // @see https://simplesamlphp.org/docs/development/simplesamlphp-upgrade-notes-1.14
    if (method_exists($session, 'getAuthority')) {
      $session->setAuthorityExpire($session->getAuthority(), 1);
    }
    else {
      foreach ($session->getAuthorities() as $authority) {
        $session->setAuthorityExpire($authority, 1);
      }
    }
    // Destroy the drupalauth4ssp user ID cookie.
    drupalauth4ssp_unset_user_cookie();

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
