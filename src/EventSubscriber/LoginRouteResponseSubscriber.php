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
 * Handles the response event for login routes.
 *
 * This subscriber ensures that, after a login process initiated by a service
 * provider is completed, the appropriate set of user attributes is passed back
 * to simpleSAMLphp.
 */
class LoginRouteResponseSubscriber implements EventSubscriberInterface {

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
   * Creates a normal login route response subscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param \Drupal\drupalauth4ssp\UserValidatorInterface $userValidator
   *   User validator.
   * @param \Drupal\drupalauth4ssp\EntityCache $userEntityCache
   *   Entity cache to retrieve user entities.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(AccountInterface $account, UserValidatorInterface $userValidator, $userEntityCache, $requestStack) {
    $this->account = $account;
    $this->userValidator = $userValidator;
    $this->$userEntityCache = $userEntityCache;
    $this->requestStack = $requestStack;
  }

  /**
   * Handles response event for login route.
   *
   * Notes: If control is passed to simpleSAMLphp, this method never returns.
   *
   * @todo Switch from using deprecated FilterResponseEvent.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Response event.
   */
  public function handleLoginResponse($event) : void {
    $request = $event->getRequest();

    // If we're not on the login route, get out.
    if ($request->attributes->get('_route') !== 'user.login') {
      return;
    }
    // If we're not logged in, get out.
    if ($this->account->isAnonymous()) {
      return;
    }
    // If this response was generated because of an exception, we don't
    // typically want to mess with things. The one exception to this rule is the
    // \Drupal\Core\Form\EnforcedResponseException exception -- this exception
    // is used for flow control by Drupal's form system, rather than for error
    // handling, so it is okay to ignore it.
    $exception = $request->attributes->get('exception');
    if ($exception && !($exception instanceof EnforcedResponseException)) {
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
