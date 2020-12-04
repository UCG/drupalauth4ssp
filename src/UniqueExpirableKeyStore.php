<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Component\Utility\Unicode;

/**
 * Represents a persistent storage system for unique expirable keys.
 *
 * Allows one to store in request-persistent storage a set of keys, all of which
 * are unique within for a given store ID. Optionally, each key may have an
 * expiry time. This key store ensures that, if some combination of the
 * tryPutKey($key, $expiry), tryTakeKey($key), and cleanupGarbage() operations
 * are executed by multiple requests with the same $key (even if these requests
 * execute these methods nearly simultaneously), at any point in time the return
 * values received by these functions will be consistent with the case in which the members of a subset of these operations
 * are executed one after another, with none being executed at the same time as
 * another. This is important for security-critical contexts in which, e.g., a
 * nonce is stored in this key store to ensure it is not used again, and while
 * storing this nonce we wish to simultaneously ensure no one else has used
 * (stored) this nonce.
 *
 * In order to make the guaranees given above, it is necessary that the MySQL
 * database be used as the main Drupal database, and that the InnoDB storage
 * engine be used for the key store tables associated with this key store. This
 * class performs checks to ensure these conditions are met, but it ultimately
 * the responsibility of the user of this module to verify these conditions.
 */
class UniqueExpirableKeyStore {

  /**
   * Default name of table underlying this key store.
   */
  public const DEFAULT_TABLE_NAME = 'drupalauth4ssp_unique_expirable_key_store';

  /**
   * Maximum length of keys.
   */
  public const MAX_KEY_LENGTH = 100;

  /**
   * Maximum length of store IDs.
  */
  public const MAX_STORE_ID_LENGTH = 100;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $databaseConnection;

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
   *   unique for a given store ID. The store ID is limited to
   *   MAX_STORE_ID_LENGTH in length.
   * @param \Drupal\Core\Database\Connection $databaseConnection
   *   Database connection.
   * @param string $tableName
   *   Name of database underlying the key store. Defaults to the
   *   DEFAULT_TABLE_NAME constant.
   * @throws \InvalidArgumentException
   *   Thrown if $tableName or $storeId is empty.
   * @throws \InvalidArgumentException
   *   Thrown if $storeId is more than MAX_STORE_ID_LENGTH characters long.
   * @throws \RuntimeException
   *   Thrown if Type of database associated with $databaseConnection not set to
   *   MySQL.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   */
  public function __construct(string $storeId, $databaseConnection, string $tableName = DEFAULT_TABLE_NAME) {
    if ($storeId === '') {
      throw new \InvalidArgumentException('$storeId is empty.');
    }
    if ($tableName === '') {
      throw new \InvalidArgumentException('$tableName is empty.');
    }
    if (mb_strlen($key) > MAX_STORE_ID_LENGTH) {
      throw new \InvalidArgumentException(sprintf('$storeId has more than %d characters.', MAX_STORE_ID_LENGTH));
    }
    $this->storeId = $storeId;
    $this->databaseConnection = $databaseConnection;
    $this->tableName = $tableName;

    $this->checkDatabaseAndStorageEngine();
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
   *   Key to attempt to insert (max size = MAX_KEY_LENGTH)
   * @param int $expiryTime
   *   Expiry Unix time stamp.
   * @return bool
   *   'TRUE' if the key was successfully inserted or updated, else 'FALSE' (see
   *   method description above).
   * @throws \InvalidArgumentException
   *   $key is empty.
   * @throws \InvalidArgumentException
   *   $expiryTime is less than zero.
   * @throws \InvalidArgumentException
   *   $key is more than MAX_KEY_LENGTH characters in length.
   */
  public function tryPutKey(string $key, int $expiryTime) : bool {
    if ($key === '') {
      throw new \InvalidArgumentException('$key is empty.');
    }
    if ($expiryTime < 0) {
      throw new \InvalidArgumentException('$expiryTime is less than zero.');
    }
    if (mb_strlen($key) > MAX_KEY_LENGTH) {
      throw new \InvalidArgumentException(sprintf('$key has more than %d characters.', MAX_KEY_LENGTH));
    }
  }

  /**
   * Attempts to verify that the DB is MySQL and table storage engine is InnoDB.
   *
   * Throws an exception if either condition appears not to be met.
   *
   * @return void
   */
  private function checkDatabaseAndStorageEngine() : void {
    // Check database type.
    if ($this->databaseConnection->databaseType() !== 'mysql') {
      throw new \RuntimeException('The database type must be MySQL to work with Drupal\\drupalauth4ssp\\UniqueExpirableKeyStore.');
    }

    // Check to ensure InnoDB is the storage engine for the table we are using
    // for this key store. We need to grab the storage type for our table of
    // choice.
    try {
      $result = $this->databaseConnection->query("SHOW TABLE STATUS WHERE Name = '{" . $this->databaseConnection->escapeTable($this->tableName) . "}'");
    }
    catch (\Exception $e) {
      throw new \RuntimeException(sprintf("Could not verify that storage type for table '%s' was set to InnoDB.", $this->tableName), 0, $e);
    }

    // Ensure we received exactly one table status record.
    if ($result->rowCount() > 1) {
      throw new \RuntimeException(sprintf("Database misconfiguration -- there appear to be multiple unique expirable key value store tables with name '%s'.", $this-tableName));
    }
    if ($result->rowCount() <= 0) {
      throw new \RuntimeException(sprintf("No expirable key value store table found with name '%s'.", $this->tableName));
    }
    // Check the 'Engine' field of the returned record.
    $record = $result->fetchAssoc();
    if (empty($record['Engine']) || Unicode::strcasecmp($record['Engine'], 'InnoDB') !== 0) {
      throw new \RuntimeException(sprintf("Could not verify that storage type for table '%s' was set to InnoDB.", $this->tableName), 0, $e);
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
          'length' => 100,
          'not null' => TRUE,
          'default' => 'store',
          'binary',
        ],
        'key' => [
          'description' => 'Key being stored. Must be unique for a given store ID.',
          'type' => 'varchar',
          'length' => 100,
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
