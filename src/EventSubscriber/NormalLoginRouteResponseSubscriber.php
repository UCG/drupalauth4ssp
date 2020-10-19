<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\AccountValidatorInterface;
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
   * Creates a normal login route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\AccountValidatorInterface $accountValidator
   *   Account validator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory.
   */
  public function __construct(AccountInterface $account, AccountValidatorInterface $accountValidator, ConfigFactoryInterface $configurationFactory) {
    $this->account = $account;
    $this->accountValidator = $accountValidator;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
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
    // Ignore anonymous users - we don't want to do anything until we have
    // actually logged in.
    if ($this->account->isAnonymous()) {
      return;
    }

    // See if user is SSO-enabled.
    if ($this->accountValidator->isAccountValid($this->account)) {
      // If this is a 302 or 303 redirect response, grab the redirect URL.
      $response = $event->getResponse();
      if ($response instanceof RedirectResponse) {
        $statusCode = $response->getStatusCode();
        if ($statusCode == Response::HTTP_FOUND || $statusCode == Response::HTTP_SEE_OTHER) {
          $redirectUrl = $response->getTargetUrl();
        }
      }
      // If we have a non-empty redirect URL, we'll try to use that for the
      // 'ReturnTo' URL. Otherwise, we'll use the home page.
      if (empty($redirectUrl)) {
        $redirectUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
      }

      // Try to create the simpleSAMLphp instance.
      $simpleSaml = new Simple($this->configuration->get('authsource'));
      // Initiate authentication.
      $simpleSaml->requireAuth(['ReturnTo' => $redirectUrl, 'KeepPost' => 'FALSE']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleNormalLoginResponse']];
  }

}
