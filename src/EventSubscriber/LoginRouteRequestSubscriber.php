<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Constants;
use Drupal\drupalauth4ssp\Helper\CookieHelpers;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use SimpleSAML\Auth\Source;
use SimpleSAML\Auth\State;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures authenticated users are redirected appropriately on login.
 *
 * Some browsers sometimes issue multiple GET requests for a resource. In order
 * to ensure we support such browsers cleanly, we redirect authenticated users
 * appropriately on user.login routes, in the same way we would redirect
 * unauthenticated users, respectively. This is to ensure that, if multiple
 * login requests are issued at nearly the same time, the user receives
 * the same behavior as when only one request is issued. We do this because,
 * taking as an example two requests issued for the user.login route at nearly
 * the same time, one of these requests might be issued by an authenticated
 * user, because one request might log the user in before the other request is
 * initiated. Similar logic applies to user.logout routes, and we implement such
 * logic in @see \Drupal\drupalauth4ssp\Controller\UserLogoutController. This
 * subscriber also ensures that the appropriate simpleSAMLphp method is called
 * if this route was navigated to by a simpleSAMLphp authentication source.
 */
class LoginRouteRequestSubscriber implements EventSubscriberInterface {

  /**
   * Account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * User attribute collector.
   *
   * @var \Drupal\drupalauth4ssp\UserAttributeCollector;
   */
  protected $userAttributeCollector;

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
   * Creates a new login route request subscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\drupalauth4ssp\EntityCache $userEntityCache
   *   Entity cache to retrieve user entities.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   * @param \Drupal\drupalauth4ssp\UserAttributeCollector
   *   User attribute collector.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, $userEntityCache, $requestStack, $userAttributeCollector) {
    $this->account = $account;
    $this->requestStack = $requestStack;
    $this->$userEntityCache = $userEntityCache;
    $this->userValidator = $userValidator;
    $this->$userAttributeCollector = $userAttributeCollector;
  }

  /**
   * Handles request event for login routes.
   *
   * Note: This method never returns if user parameters are passed back to
   * simpleSAMLphp.
   *
   * @todo Change deprecated use of GetResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Response event.
   *
   * @throws \Exception
   *   Thrown if the simpleSAMLphp stage given by
   *   \Drupal\drupalauth4ssp\Constants::SSP_LOGIN_SSP_STAGE_ID is invalid, and
   *   SSP does not have a suitable redirect URL.
   * @throws \SimpleSAML\Error\NoState
   *   Thrown if simpleSAMLphp state can't be found, and SSP does not have a
   *   suitable redirect URL.
   */
  public function handleLoginRequest($event) : void {
    $currentRequest = $this->requestStack->getCurrentRequest();

    // If we're not an authenticated user on the login route, get out, as this
    // subscriber is only designed for that case.
    $route = $event->getRequest()->attributes->get('_route');
    if ($route !== 'user.login' || $this->account->isAnonymous()) {
      return;
    }

    // If we have a query string parameter giving us a simpleSAMLphp state ID,
    // we will assume that this request has been initiated by a simpleSAMLphp
    // authentication source, and will return the appropriate parameters.

    if ($currentRequest->query->has(Constants::SSP_STATE_QUERY_STRING_KEY)) {
      // Get current user, and determine if the user is SSO-enabled.
      $user = $this->userEntityCache->get($this->account->id());
      if ($this->userValidator->isUserValid($user)) {
        // Recreate the simpleSAMLphp state, assemble the data we will be
        // passing back, and call \SimpleSAML\Auth\Source::completeAuth() with
        // the updated state.
        $sspState = State::loadState((string) $currentRequest->query->get(Constants::SSP_STATE_QUERY_STRING_KEY), Constants::SSP_LOGIN_SSP_STAGE_ID);
        foreach ($collector->getAttributes($user) as $attributeName => $attribute) {
          $sspState['Attributes'][$attributeName] = $attribute;
        }

        // Set the "is possible IdP session" cookie before proceeding.
        CookieHelpers::setIsPossibleIdpSessionCookie();
        Source::completeAuth($sspState);
        // The previous call should never return.
        assert(FALSE);
      }
    }

    $masterRequest = $this->requestStack->getMasterRequest();
    $returnUrl = Url::fromRoute('<front>')->setAbsolute()->toString();
    $event->setResponse(new RedirectResponse($returnUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod())));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::REQUEST => ['handleLoginRequest']];
  }

}
