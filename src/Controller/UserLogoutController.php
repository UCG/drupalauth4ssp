<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\drupalauth4ssp\Helper\HttpHelpers;
use Drupal\drupalauth4ssp\Helper\UrlHelpers;
use Drupal\drupalauth4ssp\UserValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller taking over the handling of the user.logout route.
 *
 * In order to ensure single logout is initiated (and the "is possible IdP
 * session cookie" destroyed for authenticated SSO-enabled users), and to ensure
 * a similar experience for SSO-enabled and non-SSO-enabled users, we intercept
 * user.logout routes here, and handle them manually.
 */
class UserLogoutController extends ControllerBase implements ContainerInjectionInterface {

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
   * Creates this user.logout controller object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current account.
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
    $this->userEntityCache = $userEntityCache;
    $this->requestStack = $requestStack;
  }

  /**
   * Contains controller logic.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Response redirecting the user either to the front page or to the single
   *   logout URL.
   */
  public function handle() : RedirectResponse {
    $masterRequest = $this->requestStack->getMasterRequest();
    $homePageUrl = Url::fromRoute('<front>')->setAbsolute()->toString();

    // Only logout if user is actually logged in.
    if (!$this->account->isAnonymous()) {
      // Determine whether the current user is SSO-enabled.
      if ($this->userValidator->isUserValid($this->userEntityCache->get($this->account->id()))) {
        // Go ahead and initiate single logout.
        // Build the single logout URL -- head back to the home page when we're
        // done.
        $singleLogoutUrl = UrlHelpers::generateSloUrl($masterRequest->getHost(), $homePageUrl);
        // Redirect to the single logout URL.
        return new RedirectResponse($singleLogoutUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod()));
      }
      else {
        // Perform logout. We don't do this if the user is SSO-enabled, because
        // it is done later when the simpleSAMLphp authentication source
        // performs its login logic.
        user_logout();
      }
    }

    // We always redirect to the home page to ensure consistency between the
    // user.logout behavior for authenticated users and that for unauthenticated
    // users. For reasons such consistency is important, @see
    // \Drupal\drupalauth4ssp\EventSubscriber\LoginRouteRequestSubscriber.
    return new RedirectResponse($homePageUrl, HttpHelpers::getAppropriateTemporaryRedirect($masterRequest->getMethod()));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('current_user'), $container->get('drupalauth4ssp.sso_user_validator'), $container->get('drupalauth4ssp.user_entity_cache'), $container->get('request_stack'));
  }

}
