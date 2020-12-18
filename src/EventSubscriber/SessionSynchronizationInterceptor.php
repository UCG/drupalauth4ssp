<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\drupalauth4ssp\Constants;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures simpleSAMLphp and Drupal sessions are synchronized.
 *
 * At the beginning of the request, ensures we don't have a (non-local) Drupal
 * session without a simpleSAMLphp session, and visa versa. This ensures we
 * have a unified representation of session state on this IdP. Otherwise, we
 * might, for instance, have a simpleSAMLphp session but no associated Drupal
 * session. In this case, something like a passive login request (from an SP)
 * would succeed, even though there is no local session at the IdP. Such
 * inconsistent representation of state is confusing to the user, and should be
 * avoided.
 */
class SessionSynchronizationInterceptor implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Validator used to ensure user is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\UserValidatorInterface
   */
  protected $userValidator;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Session manager.
   *
   * @var \Drupal\Core\Session\SessionManagerInterface;
   */
  protected $sessionManager;

  /**
   * Session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * Service to interact with simpleSAMLphp.
   *
   * @var \Drupal\drupalauth4ssp\SimpleSamlPhpLink
   */
  protected $sspLink;

    /**
   * Creates a login route interceptor instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Session\SessionManagerInterface $sessionManager
   *   Session manager.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   Session.
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink $sspLink
   *   Service to interact with simpleSAMLphp.
   */
  public function __construct(AccountInterface $account,
    UserValidatorInterface $userValidator,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    SessionManagerInterface $sessionManager, $session, $sspLink) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->sessionManager = $sessionManager;
    $this->session = $session;
    $this->sspLink = $sspLink;
  }

  /**
   * Ensures Drupal session is synchronized with simpleSAMLphp session.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event to which we have subscribed.
   * @return void
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  public function synchronizeSessionTypesOnRequest($event) : void {
    // We don't want to perform synchronization except on master requests.
    if (!$event->isMasterRequest()) {
      return;
    }

    $request = $event->getRequest();
    // We don't want ot perform synchronization when we're trying to perform an
    // SSO login
    $path = $request->getPathInfo();
    if ($path === Constants::SSO_LOGIN_PATH) {
      return;
    }

    // See if we have a simpleSAMLphp session.
    if ($this->sspLink->isAuthenticated()) {
      // Grab the user ID.
      $uid = $this->sspLink->getAttribute('uid');
      // If the two user IDs match, we're good -- the sessions are in sync.
      if ($this->account->id() === $uid) {
        return;
      }
      // Otherwise, we'll have to sync the sessions. If we are logged in, we
      // know we're logged in as the wrong user, so log out.
      if (!$this->account->isAnonymous()) {
        user_logout();
      }
      // Then attempt to load the simpleSAMLphp user.
      $userStorage = $this->entityTypeManager->getStorage('user');
      $user = $userStorage->load($uid);
      if (!$user || !$this->userValidator->isUserValid($user)) {
        // Since the user isn't valid, we should initiate single logout --
        // this user doesn't exist now or never should have been logged in.
        $sloUrl = UrlHelpers::generateSloUrl($request->getHost(), $request->getUri());
        $event->setResponse(new RedirectResponse($sloUrl, HttpHelpers::getAppropriateTemporaryRedirect($request->getMethod())));
        return;
      }
      // Attempt to log the user in.
      // Taken from src/UserSwitch.php from "Switch User" Drupal contrib mod.
      $this->sessionManager->regenerate();
      $this->account->setAccount($user);
      $this->session->set('uid', $user->id());
      // Attempt to reload the user, to see if it still exists. If it existed
      // before, but was deleted in between when we loaded in earlier and now,
      // we don't want to be logged in. Also check to ensure the user is still
      // an SSO-enabled user.
      $user = $userStorage->load($uid);
      if (!$user || !$this->userValidator->isUserValid($user)) {
        // Since the user is invalid, we should try to log out everywhere.
        // Taken from user_logout() in Drupal core "user" module. We don't
        // invoke hook_user_logout, because we didn't finish logging in.
        $this->sessionManager->destroy();
        $this->account->setAccount(new AnonymousUserSession());
        // Try to perform single logout.
        $sloUrl = UrlHelpers::generateSloUrl($request->getHost(), $request->getUri());
        $event->setResponse(new RedirectResponse($sloUrl, HttpHelpers::getAppropriateTemporaryRedirect($request->getMethod())));
      }
      else {
        //Finish the login process
        $this->moduleHandler->invokeAll('user_login', [$user]);
      }
    }
    else {
      // Log user out if logged in as non-local user.
      if (!$this->account->isAnonymous()) {
        // Check user validity -- if invalid (i.e., non-SSO user), don't log
        // user out; otherwise, do.
        if ($this->userValidator->isUserValid($this->entityTypeManager->getStorage('user')->load($this->account->id()))) {
          user_logout();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // We use priority 299. This ensures these events execute *after* Drupal's
    // default authentication (priority 300) has completed, and before (almost)
    // anything else (including the dynamic page cache). Unfortunately, there is
    // one other event with priority 299, which is used to set the current time
    // zone. However, the time zone subscriber automatically handles a change in
    // user.
    $events[KernelEvents::REQUEST][] = ['synchronizeSessionTypesOnRequest', 299];

    return $events;
  }

}
