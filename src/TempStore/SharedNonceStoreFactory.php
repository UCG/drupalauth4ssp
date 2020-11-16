<?php

namespace Drupal\drupalauth4ssp\TempStore;

use Drupal\Core\TempStore\SharedTempStoreFactory;

/**
 * Represents a factory for generating shared nonce store objects.
 */
class SharedNonceStoreFactory extends SharedTempStoreFactory {

  /**
   * Gets a shared nonce store from the factory.
   *
   * @param string $collection
   *   Collection name for key/value store.
   * @param mixed $owner
   *   (optional) Owner of the shared nonce store ('drupalauth4ssp_nonce_store_owner' by
   *   default).
   * @return void
   */
  public function get($collection, $owner = 'drupalauth4ssp_nonce_store_owner') {
    $storage = $this->storageFactory->get('tempstore.shared.drupalauth4ssp_nonces.{$collection}');
    return new SharedNonceStore($storage, $this->lockBackend, $owner, $this->requestStack, $this->expire);
  }

}
