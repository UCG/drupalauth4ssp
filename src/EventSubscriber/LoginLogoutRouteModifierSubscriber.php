<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;

/**
 * Modifies login and logout routes appropriately.
 *
 * Modifies user.login and user.logout routes so that they are accessible to
 * authenticated/unauthenticated users, respectively. This is to ensure the
 * behavior for these routes, in terms of what page a user accessing such a
 * route is redirected to, is consistent between authenticated and
 * unauthenticated users. For the reasons such consitency is important, @see
 * \Drupal\drupalauth4ssp\EventSubscriber\NormalLogoutLoginRouteRequestSubscriber.
 */
class LoginLogoutRouteModifierSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes($collection) {
    // First, modify the user.login route.
    $loginRoute = $collection->get('user.login');
    if (!empty($loginRoute)) {
      // If the route is defined, ensure anyone can access it.
      $loginRoute->setRequirements(['_access' => 'TRUE']);
    }

    // Next, modify the user.logout route.
    $logoutRoute = $collection->get('user.logout');
    if (!empty($logoutRoute)) {
      // If the route is defined, ensure anyone can access this route.
      $logoutRoute->setRequirements(['_access' => 'TRUE']);
    }
  }

}
