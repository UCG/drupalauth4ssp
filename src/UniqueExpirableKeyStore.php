<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\drupalauth4ssp\Helper\TimeHelpers;

/**
 * Represents a persistent storage system for unique expirable keys.
 *
 * Allows one to store in request-persistent storage a set of keys, all of which
 * are unique for for a given store ID. Optionally, each key may have an expiry
 * time. This key store ensures that, if some combination of the
 * tryPutKey($key, $expiry), tryTakeKey($key), and cleanupGarbage() operations
 * are executed by multiple requests with the same $key (even if these requests
 * execute these methods nearly simultaneously), at any point in time the return
 * values received by these functions will be consistent with the case in which
 * the members of a subset of these operations are executed one after another,
 * with none being executed at the same time as another. This is important for
 * security-critical contexts in which, e.g., a nonce is stored in this key
 * store to ensure it is not used again, and while storing this nonce we wish to
 * simultaneously ensure no one else has used (stored) this nonce.
 *
 * In order to make the guarantees given above, it is necessary that the MySQL
 * database be used as the main Drupal database, and that the InnoDB storage
 * engine be used for the key store tables associated with this key store. This
 * class performs runtime checks to ensure these conditions are met, but it is
 * ultimately the responsibility of the user of this module to verify these
 * conditions.
 */
class UniqueExpirableKeyStore implements GarbageCollectableInterface {

  /**
   * Default name of table underlying this key store.
   */
  public const DEFAULT_TABLE_NAME = 'drupalauth4ssp_unique_expirable_key_store';

  /**
   * Maximum length of keys.
   */
  public const MAX_KEY_LENGTH = 50;

  /**
   * Maximum length of store IDs.
   */
  public const MAX_STORE_ID_LENGTH = 100;

  /**
   * Number of times to repeat a transaction that fails due to deadlock.
   */
  protected const TRANSACTION_REPEAT_COUNT = 10;

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
   * Constructs a new \Drupal\drupalauth4ssp\UniqueExpirableKeyStore object.
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
   *
   * @throws \InvalidArgumentException
   *   Thrown if $tableName or $storeId is empty.
   * @throws \InvalidArgumentException
   *   Thrown if $storeId is more than MAX_STORE_ID_LENGTH characters long.
   * @throws \RuntimeException
   *   Thrown if type of database associated with $databaseConnection not set to
   *   MySQL.
   * @throws \RuntimeException
   *   Thrown if the database driver indicates it doesn't support transactions.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   * @throws \RuntimeException
   *   Thrown if corruption is detected in the database.
   * @throws \RuntimeException
   *   Thrown if the size of a PHP integer on this platform is not at least four
   *   bytes (such a size is needed to ensure Unix timestamps can be accurately
   *   represented).
   */
  public function __construct(string $storeId, $databaseConnection, string $tableName = self::DEFAULT_TABLE_NAME) {
    if ($storeId === '') {
      throw new \InvalidArgumentException('$storeId is empty.');
    }
    if ($tableName === '') {
      throw new \InvalidArgumentException('$tableName is empty.');
    }
    if (mb_strlen($storeId) > static::MAX_STORE_ID_LENGTH) {
      throw new \InvalidArgumentException(sprintf('$storeId has more than %d characters.', static::MAX_STORE_ID_LENGTH));
    }
    if (PHP_INT_SIZE < 4) {
      throw new \RuntimeException('The size of a PHP integer on this platform is less than four bytes.');
    }

    $this->storeId = $storeId;
    $this->databaseConnection = $databaseConnection;
    $this->tableName = $tableName;

    $this->checkDatabaseAndStorageEngine();
  }

  /**
   * Gets rid of expired keys for the store ID assocated with this object.
   *
   * A key is determined to be expired if its expiry time is less than
   * $_SERVER['REQUEST_TIME'], if it is available, or otherwise less than
   * time().
   *
   * @throws \RuntimeException
   *   Thrown if type of database associated with $databaseConnection not set to
   *   MySQL.
   * @throws \RuntimeException
   *   Thrown if the database driver indicates it doesn't support transactions.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   * @throws \RuntimeException
   *   Thrown if corruption is detected in the database.
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   *   Thrown if a database error occurs.
   */
  public function cleanupGarbage() : void {
    // Check the validity of the database and storage engine types.
    $this->checkDatabaseAndStorageEngine();

    $this->executeDatabaseTransaction(function () {
      $currentTime = TimeHelpers::getCurrentTime();
      // Delete expired keys.
      $this->databaseConnection->delete($this->tableName)
        ->condition('storeId', $this->storeId, '=')
        ->condition('expiry', $currentTime, '<')
        ->execute();
    });
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
   * A key is determined to be expired if its expiry time is less than
   * $_SERVER['REQUEST_TIME'], if it is available, or otherwise less than
   * time().
   *
   * @param string $key
   *   Key to attempt to insert (max size = MAX_KEY_LENGTH).
   * @param int $expiryTime
   *   Expiry Unix time stamp.
   *
   * @return bool
   *   'TRUE' if the key was successfully inserted or updated, else 'FALSE' (see
   *   method description above).
   *
   * @throws \InvalidArgumentException
   *   Thrown if $key is empty.
   * @throws \InvalidArgumentException
   *   Thrown if $expiryTime is less than zero.
   * @throws \InvalidArgumentException
   *   Thrown if $key is more than MAX_KEY_LENGTH characters in length.
   * @throws \InvalidArgumentException
   *   Thrown if $expiryTime is less than $_SERVER['REQUEST_TIME'], if it is
   *   available, or otherwise if it is less than time().
   * @throws \RuntimeException
   *   Thrown if type of database associated with $databaseConnection is not set
   *   to MySQL.
   * @throws \RuntimeException
   *   Thrown if the database driver indicates it doesn't support transactions.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   * @throws \RuntimeException
   *   Thrown if corruption is detected in the database.
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   *   Thrown if a database error occurs.
   */
  public function tryPutKey(string $key, int $expiryTime) : bool {
    if ($key === '') {
      throw new \InvalidArgumentException('$key is empty.');
    }
    if ($expiryTime < 0) {
      throw new \InvalidArgumentException('$expiryTime is less than zero.');
    }
    if (mb_strlen($key) > static::MAX_KEY_LENGTH) {
      throw new \InvalidArgumentException(sprintf('$key has more than %d characters.', static::MAX_KEY_LENGTH));
    }
    $currentTime = TimeHelpers::getCurrentTime();
    if ($expiryTime < $currentTime) {
      throw new \InvalidArgumentException('$expiryTime is earlier than the request and/or current time.');
    }
    // Check the validity of the database and storage engine types.
    $this->checkDatabaseAndStorageEngine();

    $couldInsertOrUpdateKeyRecord = FALSE;
    $this->executeDatabaseTransaction(function () use ($key, $expiryTime, &$couldInsertOrUpdateKeyRecord) {
      // Go ahead and lock and select the row/index corresponding to this key.
      $result = $this->databaseConnection->query('SELECT expiry FROM {' .
        $this->databaseConnection->escapeTable($this->tableName) .
        '} WHERE storeId = :storeId AND `key` = :key FOR UPDATE',
        [':storeId' => $this->storeId, ':key' => $key]);

      // Try to fetch the first expiry timestamp.
      $existingRecordExpiry = $result->fetchField(0);
      if ($existingRecordExpiry === FALSE) {
        // No rows -- go ahead and insert the key.
        $this->databaseConnection->insert($this->tableName)->fields([
          'storeId' => $this->storeId,
          'key' => $key,
          'expiry' => $expiryTime,
        ])->execute();
        $couldInsertOrUpdateKeyRecord = TRUE;
      }
      else {
        // Try to grab the 'expiry' field from the *next* record.
        $secondRecordExpiry = $result->fetchField(0);
        if ($secondRecordExpiry !== FALSE) {
          // This shouldn't happen -- we should only get one or zero records
          // returned.
          throw new \RuntimeException('Database table corrupt - invalid number of records returned from database for a given key and store ID.');
        }

        // We're good -- exactly one record was returned.
        // Check to see if the key has expired.
        if ((int) $existingRecordExpiry < $currentTime) {
          // The key has expired. Go ahead and update record with new expiry
          // time.
          $this->databaseConnection->update($this->tableName)
            ->fields(['expiry' => $expiryTime])
            ->condition('key', $key, '=')
            ->condition('storeId', $storeId, '=')
            ->execute();
          $couldInsertOrUpdateKeyRecord = TRUE;
        }
        else {
          // It appears we already have an active key whose record we can't mess
          // with.
          $couldInsertOrUpdateKeyRecord = FALSE;
        }
      }
    });

    return $couldInsertOrUpdateKeyRecord;
  }

  /**
   * Attempts to take an existing key from the store.
   *
   * This method behaves in one of the following ways:
   * 1) If a non-expired key $key exists for the current store ID, this method
   * returns TRUE, and the record corresponding to that key/store ID combination
   * is deleted.
   * 2) If an expired key $key exists for the current store ID, this method
   * returns FALSE and the record corresponding to that key/store ID combination
   * is left alone.
   * 3) If the key $key does not exist for the current store ID, this method
   * returns FALSE.
   *
   * A key is determined to be expired if its expiry time is less than
   * $_SERVER['REQUEST_TIME'], if it is available, or otherwise less than
   * time().
   *
   * @param string $key
   *   Key to attempt to take (max size = MAX_KEY_LENGTH).
   *
   * @return bool
   *   Thrown if 'TRUE' if an active key existed and was deleted, else 'FALSE'.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $key is empty.
   * @throws \InvalidArgumentException
   *   Thrown if $key is more than MAX_KEY_LENGTH characters in length.
   * @throws \RuntimeException
   *   Thrown if type of database associated with $databaseConnection not set to
   *   MySQL.
   * @throws \RuntimeException
   *   Thrown if the database driver indicates it doesn't support transactions.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   * @throws \RuntimeException
   *   Thrown if corruption is detected in the database.
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   *   Thrown if a database error occurs.
   */
  public function tryTakeKey(string $key) : bool {
    if ($key === '') {
      throw new \InvalidArgumentException('$key is empty.');
    }
    if (mb_strlen($key) > static::MAX_KEY_LENGTH) {
      throw new \InvalidArgumentException(sprintf('$key has more than %d characters.', static::MAX_KEY_LENGTH));
    }
    // Check the validity of the database and storage engine types.
    $this->checkDatabaseAndStorageEngine();

    $couldDeleteKeyRecord = FALSE;
    $this->executeDatabaseTransaction(function () use ($key, &$couldDeleteKeyRecord) {
      // Go ahead and lock and select the row/index corresponding to this key.
      $result = $this->databaseConnection->query('SELECT expiry FROM {' .
        $this->databaseConnection->escapeTable($this->tableName) .
        '} WHERE storeId = :storeId AND `key` = :key FOR UPDATE',
        [':storeId' => $this->storeId, ':key' => $key]);
      // Note that the index for the store ID/key pair will be locked *even if
      // no rows are returned from the SELECT query above*.

      // Try to fetch the first expiry timestamp.
      $existingRecordExpiry = $result->fetchField(0);
      if ($expiry === FALSE) {
        // No rows -- can't delete anything.
        $couldDeleteKeyRecord = FALSE;
      }
      else {
        // Try to grab the 'expiry' field from the *next* record.
        $secondRecordExpiry = $result->fetchField(0);
        if ($secondRecordExpiry !== FALSE) {
          // This shouldn't happen -- we should only get one or zero records
          // returned.
          throw new \RuntimeException('Database table corrupt - invalid number of records returned from database for a given key and store ID.');
        }

        // We're good -- exactly one record was returned.
        // Check to see if the key has expired.
        if ((int) $existingRecordExpiry < TimeHelpers::getCurrentTime()) {
          // Key has expired -- we won't delete it, but we do return 'FALSE'.
          $couldDeleteKeyRecord = FALSE;
        }
        else {
          // Key is active. Go ahead and delete it.
          $result = $this->databaseConnection->delete($this->tableName)
            ->condition('key', $key, '=')
            ->condition('storeId', $this->storeId, '=')
            ->execute();
          if ($result !== 1) {
            throw new \RuntimeException('Could not delete key / store ID combination.');
          }
          $couldDeleteKeyRecord = TRUE;
        }
      }
    });

    return $couldDeleteKeyRecord;
  }

  /**
   * Attempts to verify that the DB is MySQL and table storage engine is InnoDB.
   *
   * Throws an exception if either condition appears not to be met.
   *
   * @throws \RuntimeException
   *   Thrown if type of database associated with $databaseConnection not set to
   *   MySQL.
   * @throws \RuntimeException
   *   Thrown if the database driver indicates it doesn't support transactions.
   * @throws \RuntimeException
   *   Thrown if it could not be verified that the table represented by
   *   $tableName uses InnoDB as its storage engine.
   * @throws \RuntimeException
   *   Thrown if corruption is detected in the database.
   */
  protected function checkDatabaseAndStorageEngine() : void {
    // Check database type.
    if ($this->databaseConnection->databaseType() !== 'mysql') {
      throw new \RuntimeException('The database type must be MySQL to work with Drupal\\drupalauth4ssp\\UniqueExpirableKeyStore.');
    }

    // Check to see if database driver supports transactions.
    if (!$this->databaseConnection->supportsTransactions()) {
      throw new \RuntimeException('Database driver doesn\'t support transactions.');
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

    // Try to obtain returned record.
    $record = $result->fetchAssoc();
    // If there was no record...
    if (!$record) {
      throw new \RuntimeException(sprintf("No expirable key value store table found with name '%s'.", $this->tableName));
    }
    // Make sure there's not another record (should be only one table!).
    $nextRecord = $result->fetchAssoc();
    if ($nextRecord) {
      throw new \RuntimeException(sprintf("Database misconfiguration -- there appear to be multiple unique expirable key value store tables with name '%s'.", $this->tableName));
    }

    // Check the 'Engine' field of the returned record.
    if (empty($record['Engine']) || Unicode::strcasecmp($record['Engine'], 'InnoDB') !== 0) {
      throw new \RuntimeException(sprintf("Could not verify that storage type for table '%s' was set to InnoDB.", $this->tableName), 0, $e);
    }
  }

  /**
   * Executes a transaction, possibly retrying after a deadlock.
   *
   * The transaction is retried up to TRANSACTION_REPEAT_COUNT times after a
   * possible deadlock.
   *
   * @param callable $transactionExecution
   *   Function executing transaction statements.
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   *   Thrown if a database error occurs.
   */
  protected function executeDatabaseTransaction(callable $transactionExecution) {
    // Start the transaction, and repeat for up to TRANSACTION_REPEAT_COUNT
    // times if the transaction deadlocks.
    for ($i = 0; $i < static::TRANSACTION_REPEAT_COUNT; $i++) {
      // Set the appropriate transaction isolation level.
      $this->setAppropriateTransactionIsolationLevel();
      // Start the transaction.
      $transaction = $this->databaseConnection->startTransaction();
      try {
        // Execute transaction code.
        $transactionExecution();
        // Unset transaction explicitly to force transaction commit.
        unset($transaction);
        // Return to caller -- transaction is finished.
        return;
      }
      catch (DatabaseExceptionWrapper $e) {
        // Rollback transaction (should happen automatically for a 1213 MySQL
        // error code, but we do it here no matter what just to be safe).
        if (isset($transaction)) {
          $transaction->rollBack;
        }

        // Grab the inner PDO exception.
        $pdoException = $e->getPrevious();
        assert(isset($pdoException) && is_object($pdoException) && $pdoException instanceof \PDOException);

        // Check the MySQL error code -- if it corresponds to a detected
        // deadlock state (1213), or a lock acquire timeout (1205), let the
        // transaction be retried, as both those codes could be caused by
        // deadlocks. Otherwise, rethrow the exception.
        $mysqlErrorCode = (string) $pdoException->errorInfo[1];
        if ($mysqlErrorCode !== '1205' && $mysqlErrorCode !== '1213') {
          throw $e;
        }
      }
      catch (\Throwable $e) {
        // Rollback and rethrow.
        if (isset($transaction)) {
          $transaction->rollBack;
        }
        throw $e;
      }
    }

    // If we got this far, we must have retried TRANSACTION_REPEAT_COUNT times
    // and still encountered a deadlock. We'll just fail, in this case...
    // Also, roll back the transaction.
    if (isset($transaction)) {
      $transaction->rollBack;
    }

    // Throw the deadlock exception.
    assert(isset($e));
    throw $e;
  }

  /**
   * Sets the transaction isolation level to the highest level (SERIALIZABLE).
   *
   * @throws \Drupal\Core\Database\DatabaseExceptionWrapper
   *   Thrown if a database error occurs.
   */
  protected function setAppropriateTransactionIsolationLevel() : void {
    // Set the transaction level to the highest possible for an added safety
    // measure (though the exclusive locks we acquire in this class should be
    // enough to guarantee sufficient transaction isolation).
    $this->databaseConnection->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
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
          'length' => static::MAX_STORE_ID_LENGTH,
          'not null' => TRUE,
          'default' => 'store',
          'binary' => TRUE,
        ],
        'key' => [
          'description' => 'Key being stored. Must be unique for a given store ID.',
          'type' => 'varchar',
          'length' => static::MAX_KEY_LENGTH,
          'not null' => TRUE,
          'default' => 'key',
          'binary' => TRUE,
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
