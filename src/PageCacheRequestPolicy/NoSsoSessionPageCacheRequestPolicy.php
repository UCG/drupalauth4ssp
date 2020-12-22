<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\PageCacheRequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;

/**
 * Policy to prevent page cache fetch if there is a simpleSAMLphp SSO session.
 *
 * If there is an active simpleSAMLphp session, the page cache should be used.
 * This is because our session synchronization code could convert this
 * simpleSAMLphp session to an associated Drupal session.
 */
class NoSsoSessionPageCacheRequestPolicy implements RequestPolicyInterface {

  /**
   * Helper service to link with simpleSAMLphp.
   *
   * @var \Drupal\drupalauth4ssp\SimpleSamlPhpLink
   */
  protected $sspLink;

  /**
   * Creates a new NoSsoSessionPageCacheRequestPolicy instance.
   *
   * @param \Drupal\drupalauth4ssp\SimpleSamlPhpLink $sspLink
   *   Helper service to link with simpleSAMLphp.
  */
  public function __construct($sspLink) {
    $this->sspLink = $sspLink;
  }

  /**
   * {@inheritdoc}
   * @throws
   *   \Drupal\drupalauth4ssp\Exception\SimpleSamlPhpInternalConfigException
   *   Thrown if there is a problem with the simpleSAMLphp configuration.
   */
  public function check($request) {
    if ($this->sspLink->isAuthenticated()) {
      return static::DENY;
    }
    else {
      return NULL;
    }
  }

}
