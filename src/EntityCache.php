<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Retrieves entities, either by loading them or from an in-memory cache.
 *
 * To avoid the overhead of loading entities directly from the database, this
 * class automatically caches any entities that are retrieved. Each entity is
 * stored with its ID when it is initially loaded by an instance of this
 * class, and can be retrieved using its ID at a later time.
 */
class EntityCache {

  /**
   * Key/value table of entities.
   *
   * @var array
   */
  protected $cache = [];

  /**
   * Entity storage used to fill this cache.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Creates a new entity cache object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorage
   *   Entity storage used to fill this cache.
   */
  public function __construct(EntityStorageInterface $entityStorage) {
    $this->entityStorage = $entityStorage;
  }

  /**
   * Cleares this cache.
   *
   * @return void
   */
  public function clear() : void {
    $this->cache = [];
  }

  /**
   * Loads or retrieves the entity with the given ID.
   *
   * The entity is loaded if it doesn't exist in the cache, otherwise it is
   * retrieved from the cache. If the entity is loaded, it is saved to this
   * cache before this method returns.
   *
   * @param string $id
   *   Entity ID of entity to be obtained.
   *
   * @return EntityInterface
   *   Entity.
   *
   * @throws \InvalidArgumentException
   *   $id is empty.
   * @throws \InvalidArgumentException
   *   $id does not correspond to an actual entity, either in the cache or that
   *   can be loaded by Drupal.
   */
  public function get(string $id) : EntityInterface {
    if ($id === '') {
      throw new \InvalidArgumentException('$id is empty.');
    }

    if (array_key_exists($id, $this->cache)) {
      // Return the cached entity.
      assert($this->cache[$id]);
      return $this->cache[$id];
    }
    else {
      // Grab the entity from storage.
      $entity = $this->entityStorage->load($id);
      if (!$entity) {
        throw new \InvalidArgumentException('No entity found for id \'' . $id . '\'.');
      }

      $this->cache[$id] = $entity;
      return $entity;
    }
  }

  /**
   * Checks if the cache has an entity with ID $id.
   *
   * @param string $id
   *   Entity ID to check for.
   *
   * @return bool
   *   Returns 'TRUE' if cache does have an entity with ID $id, else returns
   *   'FALSE'.
   */
  public function has(string $id) : bool {
    if ($id === '') {
      return FALSE;
    }
    else {
      return array_key_exists($id, $this->cache);
    }
  }

  /**
   * Removes the entity with ID $id from the cache.
   *
   * @param string $id
   *   Entity ID of entity to remove.
   *  
   * @return bool
   *   Returns 'TRUE' if the entity existed in the cache and was removed, else
   *   returns 'FALSE'.
   */
  public function remove(string $id) : bool {
    if ($id !== '' && array_key_exists($id, $this->cache)) {
      unset($this->cache[$id]);
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Creates an entity cache for a given entity type.
   *
   * @param string $entityTypeId
   *   Entity type ID of the entity type for which we wish to create a cache.
   * @param EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager used to retrieve the entity storage for this entity
   *   type.
   *
   * @return \Drupal\drupalauth4ssp\EntityCache
   *   The entity cache created for this entity type.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler associated with this entity type ID
   *   couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type for the given ID doesn't exist.
   * @throws \InvalidArugmentException
   *    Thrown if $entityTypeId is empty.
   */
  public static function createForEntityType(string $entityTypeId, EntityTypeManagerInterface $entityTypeManager) {
    if ($entityTypeId === '') {
      throw new \InvalidArgumentException('$entityTypeId is empty.');
    }

    return new EntityCache($entityTypeManager->getStorage($entityTypeId));
  }

}
