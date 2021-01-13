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
 * Handles the kernel.response event for login (user.login) routes.
 *
 * This subscriber ensures that, after a login process initiated by a service
 * provider is completed, the appropriate set of user attributes is passed back
 * to simpleSAMLphp. Also ensures post-login redirect behavior consistent with
 * @see \Drupal\drupalauth4ssp\EventSubscriber\StandardLogoutLoginRouteRequestSubscriber.
 */
class LoginRouteResponseSubscriber implements EventSubscriberInterface {

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
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cacheKillSwitch
   *   Kill switch with which to disable caching.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, EntityTypeManagerInterface $entityTypeManager, $requestStack, $cacheKillSwitch) {
    $this->account = $account;
    $this->cacheKillSwitch = $cacheKillSwitch;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->userValidator = $userValidator;
  }

  /**
   * Handles response event for login route.
   *
   * Notes: If control is passed to simpleSAMLphp, this method never returns.
   * @todo Switch for Drupal 9 from using deprecated FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function handleNormalLoginResponse($event) : void {
    $request = $event->getRequest();

    // If we're not on the login route, get out.
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

    // See if our handler of hook_user_login properly set a variable indicating
    // the user who was logged in. We only want to run this event handler as
    // part of the normal login process associated with the user.login route -- so we check
    // to ensure this variable is set properly (that is, has the same ID as the
    // current user) as a sanity check. If this variable were not set properly,
    // hook_user_login would not have been invoked properly, indicating an
    // abnormality in the login process.
    $loginHookUser = &drupal_static('drupalauth4ssp_var_loginHookUser');
    if ($this->account->isAnonymous() || empty($loginHookUser) || $loginHookUser->id() !== $this->account->id()) {
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
