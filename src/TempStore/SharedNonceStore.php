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
 * cryptographic nonces. This store has the following features:
 *
 * 1) Nonce expiry - allows one to set expiry times for the nonces stored using
 * this store.
 * 2) Atomic nonce set operation - allows one to simultaneously check for the
 * existence of and set nonces.
 * 3) Atomic nonce retrieval operation - allows one to simultaneously check for
 * the existence of and delete nonces.
 */
class SharedNonceStore extends SharedTempStore {

  public function __construct(KeyValueStoreExpirableInterface $storage, LockBackendInterface $lockManager, $owner, $requestStack, $expire = 604800) {
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
   *   $nonce is empty
   */
  public function setNonce(string $nonce) : bool {
    if ($nonce === '') {
      throw new \InvalidArgumentException('$nonce cannot be empty.');
    }

    // Store nonce in key; don't store any value.
    return parent::setIfNotExists($nonce, NULL);
  }

  /**
   * Takes the given nonce from the collection if it is accessible.
   *
   * @param string $nonce
   *   Nonce to try to take.
   * @return bool
   *   'TRUE' if nonce was successfully deleted and existed; 'FALSE' if nonce
   *   did not exist or was in the process of being deleted by someone else.
   */
  public function takeNonce(string $nonce) : bool {
    $lockTimeout = 30; // 30 seconds.
    $lockTimeoutSafetyMargin = 2;

    // Record initial timestamp.
    $startTime = time();

    // Acquire a lock in order to perform the check-and-delete operation.
    if (!$this->lockBackend->acquire($nonce, $lockTimeout)) {
      // Someone else is attempting to perform the same delete operation. Don't
      // interfere; indicate we failed to take this nonce.
      return FALSE;
    }

    // Delete the nonce if it exists.
    $nonceExists = (bool) $this->storage->get($nonce);
    if ($nonceExists) {
      $this->storage->delete($nonce);
    }

    // Release the lock.
    $this->lockBackend->release($nonce);
    // If the lock expired (or came close to expiring; we include a margin of
    // $lockTimeoutSafetyMargin [2 s] to deal with rounding errors), go ahead
    // and indicate a failure to successfully take the nonce -- we cannot be
    // certain someone else didn't take this nonce after our lock expired.
    if (time() >= ($startTime + $lockTimeout - $lockTimeoutSafetyMargin)) {
      return FALSE;
    }
    else {
      return $nonceExists;
    }
  }

}
