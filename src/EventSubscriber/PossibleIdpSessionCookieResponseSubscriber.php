<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\Helper\CookieHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets/clears "poss. IdP sess." cookie in certain cases.
 *
 * After handling a user.login or user.logout route, this subscriber may be
 * used to set the "is possible IdP session" cookie in correspondence with the
 * current Drupal authentication status. Additionally, this cookie is updated
 * on all routes if its value on the request is inconsistent with what it should
 * be.
 */
class PossibleIdpSessionCookieResponseSubscriber implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Entity cache to retrieve user entities.
   *
   * @var \Drupal\drupalauth4ssp\EntityCache
   */
  protected $userEntityCache;

  /**
   * Validator used to ensure user is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\UserValidatorInterface
   */
  protected $userValidator;

  /**
   * Creates a new logout route request subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\drupalauth4ssp\EntityCache $userEntityCache
   *   Entity cache to retrieve user entities.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, ConfigFactoryInterface $configurationFactory, UserValidatorInterface $userValidator, $userEntityCache, $requestStack) {
    $this->account = $account;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
    $this->userValidator = $userValidator;
    $this->userEntityCache = $userEntityCache;
    $this->requestStack = $requestStack;
  }

  /**
   * Handles response event.
   *
   * @todo Change deprecated use of GetResponseEvent.
   *
   * @todo Switch from using deprecated FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function handleResponse($event) : void {
    // Determine if "is possible IdP session" cookie should be set.
    if ($this->account->isAnonymous()) {
      $isPossibleIdpSession = FALSE;
    }
    else {
      // Cookie should be set if user is SSO-enabled user.
      $isPossibleIdpSession = $this->userValidator->isUserValid($this->userEntityCache->get($this->account->id()));
    }

    // Determine "is possible IdP session" cookie name.
    $cookieName = $this->configuration->get('is_possible_idp_session_cookie_name');

    $masterRequest = $this->requestStack->getMasterRequest();

    // If we're not either 1) on the user.login route, 2) on the user.logout
    // route, or 3) handling a request that has the "is possible IdP session"
    // cookie set incorrectly, get out of here.

    $route = $event->getRequest()->attributes->get('_route');
    if ($route !== 'user.logout' && $route !== 'user.login') {
      // Check to see if the "is possible IdP session" cookie is set correctly
      // -- get out of here if so; we need do nothing more.
      if ($isPossibleIdpSession) {
        if ($masterRequest->cookies->has($cookieName) && $masterRequest->cookies->get($cookieName) === 'TRUE') {
          return;
        }
      }
      else {
        if (!$masterRequest->cookies->has($cookieName)) {
          return;
        }
      }
    }

    // Go ahead and set the cookie as necessary.
    $currentResponse = $event->getResponse();
    $cookieDomain = CookieHelpers::getIsPossibleIdpSessionCookieDomain();
    if ($isPossibleIdpSession) {
      $cookie = new Cookie($cookieName, 'TRUE', CookieHelpers::getIsPossibleIdpSessionCookieExpiration(), '/', $cookieDomain);
      $currentResponse->headers->setCookie($cookie);
    }
    else {
      $currentResponse->headers->clearCookie($cookieName, '/', $cookieDomain);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => ['handleResponse']];
  }

}
