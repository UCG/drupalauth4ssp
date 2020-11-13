<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\TempStore;

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\TempStore\SharedTempStore;

/**
 * Represents a temporary storage mechanism for storing one-time nonces.
 *
 * This temporary storage class is designed for the storage of one-time
 * cryptographic nonces. It associates all the nonces with the same owner
 * ('nonce_owner') by default, but allows one to specify an alternate owner if
 * desired. This store has the following features:
 *
 * 1) Nonce expiry - allows one to set expiry times for the nonces stored using
 * this store.
 * 2) Atomic nonce set operation - allows one to simultaneously check for the
 * existence of and set nonces.
 * 3) Atomic nonce retrieval operation - allows one to simultaneously check for
 * the existenc eof and delete nonces.
 */
class SharedNonceStore extends SharedTempStore {

  public function __construct(KeyValueStoreExpirableInterface $storage, LockBackendInterface $lockManager, $owner = 'nonce_owner', $requestStack, $expire = 604800) {
    parent::__construct($storage, $lockManager, $owner, $requestStack, $expire);
  }

  /**
   * Stores the given nonce, unless it already exists.
   *
   * @param string $nonce
   *   Nonce to store.
   * @return bool
   *   'TRUE' if nonce was successfully stored and did not exist; 'FALSE' if
   *   nonce already existed.
   * @throws \InvalidArgumentException
   *   $nonce is empty.
   */
  public function setNonce(string $nonce) : bool {
    if ($nonce === '') {
      throw new \InvalidArgumentException('$nonce cannot be empty.');
    }
  }

}
