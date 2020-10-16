<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\AccountValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles SSO log in route when user is already logged in or out.
 *
 * By default, Drupal returns a 403 when someone tries to access a login route
 * when the user is logged in. Here, for the 'drupalauth4ssp.ssoLogin' route, we
 * instead check to see if the current user is logged in a valid (SSO-enabled)
 * user, and, if so, set the appropriate user cookie (for the drupalauth
 * simpleSAMLphp module). Also redirects the user to finish the SSO login
 * process. If we did not do this, if we happened to be logged in to Drupal but
 * not to simpleSAMLphp, SP-initiated SSO would fail (even though there is a
 * valid Drupal session at the IdP).
 *
 */
class SsoLoginRouteInterceptor implements EventSubscriberInterface {

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
   * Helper service to obtain and determine if 'ReturnTo' URL can be used.
   *
   * @var \Drupal\drupalauth4ssp\Helper\ReturnToUrlManager;
   */
  protected $returnToUrlManager;

  /**
   * Creates a login route interceptor instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account
   * @param \Drupal\drupalauth4ssp\AccountValidatorInterface $accountValidator
   *   Account validator
   * @param \Drupal\drupalauth4ssp\Helper\ReturnToUrlManager $returnToUrlManager
   *   Helper service to obtain and determine if 'ReturnTo' URL can be used
   */
  public function __construct(AccountInterface $account, AccountValidatorInterface $accountValidator, $returnToUrlManager) {
    $this->account = $account;
    $this->accountValidator = $accountValidator;
    $this->returnToUrlManager = $returnToUrlManager;
  }

  /**
   * Reacts to request event.
   *
   * If we are on the SSO login route, checks to see if user is already logged
   * in. If so, checks if current user can be used to perform simpleSAMLphp
   * login. If so, sets drupalauth4ssp user ID cookie appropriately. Then issues
   * redirect (if not already logged in).
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event to which we have subscribed.
   * @return void
   */
  public function handleBadLoginStatus($event) : void {
    // If we are not on the SSO login route, get out.
    $request = $event->getRequest();
    if ($request->attributes->get('_route') != 'drupalauth4ssp.ssoLogin') {
      return;
    }
    // If user is anonymous, get out of here.
    if ($this->account->isAnonymous()) {
      return;
    }
    // Otherwise, check to see if we are allowed to perform SSO login.
    if ($this->accountValidator->isAccountValid($this->account)) {
      // We have an SSO-enabled user! We will have to try to pass on his ID.
      drupalauth4ssp_set_user_cookie($this->account);
      // Now, redirect the user to the 'ReturnTo' URL if possible.
      if ($returnToUrlManager->isReturnUrlValid()) {
        $event->setResponse(new RedirectResponse($returnToUrlManager->getReturnUrl()));
      }
      else {
        // Return 403.
        throw new AccessDeniedHttpException('Cannot access SSO login route from authenticated user without return URL.');
      }
    }
    else {
      // Return 403.
      throw new AccessDeniedHttpException('Cannot access SSO login route from local-only authenticated user.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['handleBadLoginStatus']];
  }

}