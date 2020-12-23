<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles SSO log in route when user is already logged in.
 *
 * When the user is already logged in, visiting the drupalauth4ssp.ssoLogin
 * route should not bring up the normal login screen or initiate the normal
 * login process. Instead, for such cases, we check to see if the current Drupal
 * user is a valid (SSO-enabled) user, and, if so, set the appropriate user
 * cookie (for the drupalauth simpleSAMLphp module) and redirect the user
 * appropriately -- just as would happen if the user logged in by visiting the
 * drupalauth4ssp.ssoLogin route and filling out the login form. If we did not
 * do this, if we happened to be logged in to Drupal but not to simpleSAMLphp,
 * SP-initiated SSO would fail (even though there is a valid Drupal session at
 * the IdP).
 */
class SsoLoginRouteInterceptor implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

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
   * Helper service to obtain and determine if 'ReturnTo' URL can be used.
   *
   * @var \Drupal\drupalauth4ssp\Helper\UrlHelperService;
   */
  protected $urlHelper;

  /**
   * Validator used to ensure user is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\UserValidatorInterface
   */
  protected $userValidator;

  /**
   * Creates a login route interceptor instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\Helper\UrlHelperService $urlHelper
   *   Helper service to obtain and determine if 'ReturnTo' URL can be used.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, EntityTypeManagerInterface $entityTypeManager, $requestStack, $urlHelper) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->entityTypeManager = $entityTypeManager;
    $this->requestStack = $requestStack;
    $this->urlHelper = $urlHelper;
  }

  /**
   * Reacts to request event.
   *
   * If we are on the SSO login route, checks to see if user is already logged
   * in. If so, checks if current user can be used to perform simpleSAMLphp
   * login. If so, sets drupalauth4ssp user ID cookie appropriately. Then issues
   * redirect.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The request event to which we have subscribed.
   * @return void
   * @throws \RuntimeException
   *   Thrown if unable to generate the user ID cookie nonce.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if could not identify valid return URL from the query string.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown if current user is a local authenticated user.
   * @throws \Exception
   *   Thrown if something is wrong with the simpleSAMLphp authentication source
   *   configuration.
   * @throws \SimpleSAML\Error\CriticalConfigurationError
   *   Thrown if something is wrong with the simpleSAMLphp configuration.
   */
  public function handleSsoLoginRequest($event) : void {
    // If we are not on the SSO login route, get out.
    $request = $event->getRequest();
    if ($request->attributes->get('_route') !== 'drupalauth4ssp.ssoLogin') {
      return;
    }
    // If user is anonymous, get out of here.
    if ($this->account->isAnonymous()) {
      return;
    }

    $masterRequest = $this->requestStack->getMasterRequest();

    // Otherwise, check to see if current user is an SSO user.
    if ($this->userValidator->isUserValid($this->entityTypeManager->getStorage('user')->load($this->account->id()))) {
      // We have an SSO-enabled user! We will have to try to pass on his ID to
      // the drupalauth simpleSAMLphp module.
      drupalauth4ssp_set_user_cookie($this->account);
      // Next, redirect the user to the 'ReturnTo' URL if possible.
      if ($this->urlHelper->isReturnToUrlValid()) {
        $event->setResponse(new RedirectResponse($this->urlHelper->getReturnToUrl(), HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
      }
      else {
        // Return 403.
        throw new AccessDeniedHttpException('Cannot access SSO login route from authenticated user without valid return URL.');
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
    return [KernelEvents::REQUEST => ['handleSsoLoginRequest']];
  }

}
