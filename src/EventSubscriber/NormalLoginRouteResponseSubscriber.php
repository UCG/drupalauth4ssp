<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\AccountValidatorInterface;
use Drupal\drupalauth4ssp\SimpleSamlPhpLink;
use SimpleSAML\Auth\Simple;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures SSP login is initiated (if applicable) for non-SSO login routes.
 *
 * Normally, logging in through the default route ('user.login') will not create
 * a simpleSAMLphp session. Since we would like there to be a simpleSAMLphp
 * session whenever the user logs in to an SSO-enabled account, this subscriber
 * intercepts these login requests just before the response is sent. It then
 * determines where the user is being redirected, and performs simpleSAMLphp
 * authentication with the 'ReturnURL' parameter set to the redirect URL (or to
 * the home page if no such URL exists).
 */
class NormalLoginRouteResponseSubscriber implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Validator used to ensure account is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\AccountValidatorInterface
   */
  protected $accountValidator;

  /**
   * DrupalAuth for SimpleSamlPHP configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

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
   * Creates a normal login route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\AccountValidatorInterface $accountValidator
   *   Account validator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory.
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService $urlHelper
   *   URL helper service.
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink
   *   Service to interact with simpleSAMLphp.
   */
  public function __construct(AccountInterface $account, AccountValidatorInterface $accountValidator, ConfigFactoryInterface $configurationFactory, $urlHelper, $sspLink) {
    $this->account = $account;
    $this->accountValidator = $accountValidator;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->urlHelper = $urlHelper;
    $this->sspLink = $sspLink;
  }

  /**
   * Handles response event for standard login routes.
   * 
   * Notes: This method breaks the Symfony request-response flow. Also, if
   * simpleSAMLphp authentication is required, this method doesn't return.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   * @return void
   * @throws \SimpleSAML\Error\CriticalConfigurationError
   *   Thrown if something was wrong with the simpleSAMLphp configuration.
   */
  public function handleNormalLoginResponse($event) : void {
    $request = $event->getRequest();
    if (!$event->isMasterRequest()) {
      return;
    }

    // If we're not using the default login route, get out.
    if ($request->attributes->get('_route') != 'user.login') {
      return;
    }
    // If this response was generated because of an exception, we don't want to
    // mess with things; get out.
    if ($request->attributes->get('exception')) {
      return;
    }
    // If we're not actually logged in, get out.
    if ($this->account->isAnonymous()) {
      return;
    }
    // See if our handler of hook_user_logout set a flag indicating we should
    // proceed.
    $shouldInitiateLogout = &drupal_static('drupalauth4ssp_var_shouldInitiateSspLogout');
    if (!isset($shouldInitiateLogout) || !$shouldInitiateLogout) {
      return;
    }
    // If user isn't SSO-enabled, get out.
    if (!$this->accountValidator->isAccountValid($this->account)) {
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

    // Initiate authentication. Returns and continue if already logged in.
    $sspLink->initiateAuthenticationIfNecessary(['ReturnTo' => $returnUrl, 'KeepPost' => 'FALSE']);
    // For consistency, initiate redirect to $returnUrl even if we were already
    // authenticated.
    $event->setResponse(new RedirectResponse($returnUrl));
    $event->stopPropagation();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleNormalLoginResponse']];
  }

}
