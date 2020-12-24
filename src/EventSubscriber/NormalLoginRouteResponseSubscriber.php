<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\EnforcedResponseException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures SSP login is initiated (if applicable) for non-SSO login routes.
 *
 * Normally, logging in through the default route ('user.login') will not create
 * a simpleSAMLphp session. Since we would like there to be a simpleSAMLphp
 * session whenever the user logs in to an SSO-enabled account, this subscriber
 * intercepts these login requests just before the response is sent. If
 * necessary, this subscriber performs simpleSAMLphp authentication with the
 * 'ReturnTo' parameter set appropriately (to the home page). If no SSP
 * authentication is necessary, this subscriber simply redirects to the home
 * page if appropriate (see the event handler method herein).
 */
class NormalLoginRouteResponseSubscriber implements EventSubscriberInterface {

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
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Request stack.
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
   * Validator used to ensure user is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\UserValidatorInterface
   */
  protected $userValidator;

  /**
   * Creates a normal login route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink $sspLink
   *   Service to interact with simpleSAMLphp.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cacheKillSwitch
   *   Kill switch with which to disable caching.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, EntityTypeManagerInterface $entityTypeManager, $requestStack, $sspLink, $cacheKillSwitch) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->sspLink = $sspLink;
    $this->cacheKillSwitch = $cacheKillSwitch;
  }

  /**
   * Handles response event for standard login routes.
   *
   * Notes: If simpleSAMLphp authentication is initiated, this method doesn't
   * return.
   * @todo Switch for Drupal 9 from using deprecated FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   *
   * @throws \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  public function handleNormalLoginResponse($event) : void {
    $request = $event->getRequest();

    // If we're not using the default login route, get out.
    if ($request->attributes->get('_route') !== 'user.login') {
      return;
    }

    // We don't want any caching.
    $this->cacheKillSwitch->trigger();

    // If this response was generated because of an exception, we don't
    // typically want to mess with things. The one exception to this rule is the
    // \Drupal\Core\Form\EnforcedResponseException exception -- this exception
    // is used for flow control by Drupal's form system, rather than for error
    // handling, so it is okay to ignore it.
    $exception = $request->attributes->get('exception');
    if ($exception && !($exception instanceof EnforcedResponseException)) {
      return;
    }
    // If we're not actually logged in, get out.
    if ($this->account->isAnonymous()) {
      return;
    }
    // See if our handler of hook_user_login set a flag indicating we should
    // proceed. We only want to initiate SSP login as part of the normal login
    // process associated with the user.login route -- so we check to ensure
    // this variable is set properly as a sanity check (if this variable were
    // not set properly, hook_user_login would not have been invoked properly,
    // so something is abnormal about the login process).
    $shouldInitiateLogin = &drupal_static('drupalauth4ssp_var_shouldInitiateSspLogin');
    if (!isset($shouldInitiateLogin) || !$shouldInitiateLogin) {
      return;
    }

    // We will be redirecting to the home page.
    $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();

    $masterRequest = $this->requestStack->getMasterRequest();

    // If user isn't SSO-enabled, initiate redirect to $returnUrl anyway for the
    // sake of consistency.
    if (!$this->userValidator->isUserValid($this->entityTypeManager->getStorage('user')->load($this->account->id()))) {
      $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
      return;
    }

    // Initiate SSP authentication. Returns and continues if already logged in.
    $this->sspLink->initiateAuthenticationIfNecessary($returnUrl);
    // For consistency, initiate redirect to $returnUrl even if we were already
    // authenticated.
    $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleNormalLoginResponse']];
  }

}
