<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Lock\LockBackendInterface;

/**
 * Represents a persistent storage system for unique expirable keys.
 *
 * Allows one to store in request-persistent storage a set of keys, all of which
 * are unique within for a given store ID. Optionally, each key may have an
 * expiry time. This key store ensures that, if some combination of the
 * tryPutKey() and tryTakeKey() operations are executed by multiple requests
 * (even if these requests execute these methods nearly simultaneously), at any
 * point in time the return values received by these functions will be
 * consistent with the case in which the members of a subset of these operations
 * are executed one after another, with none being executed at the same time as
 * another. This is important for security-critical contexts in which, e.g., a
 * nonce is stored in this key store to ensure it is not used again, and while
 * storing this nonce we wish to simultaneously ensure no one else has used
 * (stored) this nonce.
 *
 */
class UniqueExpirableKeyStore {

  /**
   * Default name of table underlying this key store.
   */
  public const DEFAULT_TABLE_NAME = 'drupalauth4ssp_unique_expirable_key_store';

  private const SYNCHRONIZATION_LOCK_PREFIX = 'drupalauth4ssp_key_store_sync_lock_';

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseConnection;

  /**
   * Synchronization lock manager.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lockManager;

  /**
   * ID of the store collection.
   *
   * @var string
   */
  protected $storeId;

  /**
   * Name of the database table underlying this store.
   *
   * @var string
   */
  protected $tableName;

  /**
   * Constructs a new Drupal\drupalauth4ssp\UniqueExpirableKeyStore object.
   *
   * @param string $storeId
   *   Identifier of the key store associated with this object. All keys are
   *   unique for a given store ID. The store ID is limited to 256 characters.
   * @param \Drupal\Core\Lock\LockBackendInterface $lockManager
   *   Lock manager.
   * @param \Drupal\Core\Database\Connection $databaseConnection
   *   Database connection.
   * @param string $tableName
   *   Name of database underlying the key store. Defaults to the
   *   DEFAULT_TABLE_NAME constant.
   * @throws \InvalidArgumentException
   *   Thrown if $tableName or $storeId is empty.
   * @throws \InvalidArgumentException
   *   Thrown if $storeId is more than 255 characters long.
   */
  public function __construct(string $storeId, LockBackendInterface $lockManager, $databaseConnection, string $tableName = DEFAULT_TABLE_NAME) {
    if ($storeId === '') {
      throw new \InvalidArgumentException('$storeId is empty.');
    }
    if ($tableName === '') {
      throw new \InvalidArgumentException('$tableName is empty.');
    }
    if (mb_strlen($tableName) > 255) {
      throw new \InvalidArgumentException('$tableName has more than 255 characters.');
    }
    $this->storeId = $storeId;
    $this->databaseConnection = $databaseConnection;
    $this->tableName = $tableName;
    $this->lockManager = $lockManager;
  }

  /**
   * Attempts to place a new key in the store.
   *
   * This method behaves in one of the following ways:
   * 1) If a non-expired key $key exists for the current store ID, this method
   * returns FALSE and the non-expired key's expiry time is not touched.
   * 2) If an expired key $key exists for the current store ID, this method
   * returns TRUE, and the expired key's expiry time is changed to $expiryTime.
   * 3) If the key $key does not exist for the current store ID, this method
   * returns TRUE, and $key is inserted into the storage table, together with
   * the expiry time $expiryTime.
   *
   * To determine if a key has expired, $expiryTime is compared with
   * $_SERVER['REQUEST_TIME'].
   *
   * @param string $key
   *   Key to attempt to insert (255 characters max).
   * @param int $expiryTime
   *   Expiry Unix time stamp.
   * @return bool
   *   'TRUE' if the key was successfully inserted or updated, else 'FALSE' (see
   *   method description above).
   * @throws \InvalidArgumentException
   *   $key is empty.
   * @throws \InvalidArgumentException
   *   $expiryTime is less than zero.
   */
  public function tryPutKey(string $key, int $expiryTime) : bool {
    if ($key === '') {
      throw new \InvalidArgumentException('$key is empty.');
    }
    if ($expiryTime < 0) {
      throw new \InvalidArgumentException('$expiryTime is less than zero.');
    }

    
  }

  /**
   * Gets the table schema for constructing a table for this key store.
   *
   * Can be used in an implementation of hook_schema() to generate a valid
   * expirable key store table.
   *
   * @return array
   *   Schema definition for unique key store table.
   */
  public static function getSchema() : array {
    return [
      'description' => 'Stores unique keys and expiry dates for the drupalauth4ssp unique expirable key store.',
      'fields' => [
        'storeId' => [
          'description' => 'ID of expirable key value store. There can be multiple IDs in this table.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'store',
          'binary',
        ],
        'key' => [
          'description' => 'Key being stored. Must be unique for a given store ID.',
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'default' => 'key',
          'binary',
        ],
        'expiry' => [
          'description' => 'Expiry time (since Unix epoch in seconds) of key/store ID combination. Defaults to largest possible value.',
          'type' => 'int',
          'size' => 'normal',
          'default' => 2147483647,
          'unsigned' => TRUE,
        ],
      ],
      'primary key' => ['storeId', 'key'],
    ];
  }

}
