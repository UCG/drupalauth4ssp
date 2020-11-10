<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\PageCacheRequestPolicy;

use Drupal\Core\PageCache\RequestPolicyInterface;

/**
 * Policy to prevent page cache fetch if there is a simpleSAMLphp SSO session.
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
