<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\drupalauth4ssp\AccountValidatorInterface;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
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
 * session. In this case, something like passive login requests (from a SP)
 * would succeed, even though there is no local session at the IdP. Such
 * inconsistent representation of state is to be avoided.
 */
class SessionSynchronizationInterceptor implements EventSubscriberInterface {

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
   * Creates a login route interceptor instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\AccountValidatorInterface $accountValidator
   *   Account validator.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler.
   * @param \Drupal\Core\Session\SessionManagerInterface $sessionManager
   *   Session manager.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   Session.
   */
  public function __construct(AccountInterface $account,
    AccountValidatorInterface $accountValidator,
    ConfigFactoryInterface $configurationFactory,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    SessionManagerInterface $sessionManager, $session) {
    $this->account = $account;
    $this->accountValidator = $accountValidator;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->sessionManager = $sessionManager;
    $this->session = $session;
  }

  /**
   * Ensures Drupal session is synchronized with simpleSAMLphp session.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event to which we have subscribed.
   * @return void
   * @throws \SimpleSAML\Error\CriticalConfigurationError
   *   Thrown if something was wrong with the simpleSAMLphp configuration.
   */
  public function synchronizeSessionTypesOnRequest($event) : void {
    $request = $event->getRequest();

    // Try to create a simpleSAMLphp instance.
    $simpleSaml = new Simple($this->configuration->get('authsource'));
    // See if we have a simpleSAMLphp session.
    if ($simpleSaml->isAuthenticated()) {
      // Grab the user ID from the authname. First, grab all attributes.
      $attributes = $simpleSaml->getAttributes();
      $uid = (int) $attributes['uid'][0];
      //$uid should never be zero.
      assert($uid !== 0);
      // If the two user IDs match, we're good -- the sessions are in sync.
      if ($this->account->id() === $uid) {
        return;
      }
      // Otherwise, we need to sync the two sessions. Start by attempting to
      // load the simpleSAMLphp user.
      $user = $entityTypeManager->getStorage('user')->load($uid);
      if (!$this->account->isAnonymous()) {
        // Log out current user.
        user_logout();
      }
      // Log in new user.
      // Taken from src/  UserSwitch.php from "Switch User" Drupal contrib module.
      $this->sessionManager->regenerate();
      $this->account->setAccount($user);
      $this->session->set('uid', $user->id());
      // Now, reload the user to ensure it exists, and check its validity. We do
      // this after we have already logged in the user to avoid race conditions.
      $user = $entityTypeManager->getStorage('user')->load($uid);
      if (!isset($user) || !$this->accountValidator->isAccountValid($this->account)) {
        // Then we are in trouble. We should immediately log out.
        user_logout();
        // And perform single logout.
        $sloUrl = UrlHelpers::generateSloUrl($request->getHost(), $request->getUri());
        $event->setResponse(new RedirectResponse($sloUrl));
      }
      else {
        // Invoke hook_user_login.
        $this->moduleHandler->invokeAll('user_login', [$user]);
      }
    }
    else {
      // Log user out if logged in as non-local user.
      if (!$this->account->isAnonymous()) {
        // Check user validity -- if invalid (i.e., non-SSO user), don't log
        // user out; otherwise, do.
        if ($this->accountValidator->isAccountValid($this->account)) {
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
