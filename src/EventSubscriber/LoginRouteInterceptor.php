<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\drupalauth4ssp\AccountValidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
class LoginRouteInterceptor implements EventSubscriberInterface {

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
   * DrupalAuth for SimpleSamlPHP configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $configuration;

  /**
   * Path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Validator used to ensure account is SSO-enabled.
   *
   * @var \Drupal\drupalauth4ssp\AccountValidatorInterface
   */
  protected $accountValidator;

  /**
   * Creates a login route interceptor instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configurationFactory
   *   Configuration factory
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   Path matcher
   * @param \Drupal\drupalauth4ssp\AccountValidatorInterface $accountValidator
   *   Account validator
   */
  public function __construct(AccountInterface $account, EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configurationFactory, PathMatcherInterface $pathMatcher, AccountValidatorInterface $accountValidator) {
    $this->account = $account;
    $this->entityTypeManager = $entityTypeManager;
    $this->pathMatcher = $pathMatcher;
    $this->accountValidator = $accountValidator;
    $this->configuration = $configurationFactory->get('drupalauth4ssp.settings');
  }

  /**
   * React to request event.
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
    if ($this->account->id == 0) {
      return;
    }
    // Otherwise, check to see if we are allowed to perform SSO login.
    if ($this->accountValidator->isAccountValid($account)) {
      // We have an SSO-enabled user! We will have to try to pass on his ID.
      drupalauth4ssp_set_user_cookie($account);
      // Now, redirect the user. This is adapted from
      // drupalauth4ssp_user_login_submit() in drupalauth4ssp.module.
      $returnToUrl = $request->query->get('ReturnTo');
      $returnToAllowedList = $this->configuration->get('returnto_list');
      if ($pathMatcher->matchPath($returnToUrl, $returnToAllowedList)) {
        $event->setResponse(new RedirectResponse($returnToUrl));
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
