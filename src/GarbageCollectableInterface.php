<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

/**
 * Defines an object that has a method for collecting garbage.
 */
interface GarbageCollectableInterface {

  /**
   * Cleanup garbage associated with this object.
   *
   * @return void
   */
  public function cleanupGarbage() : void;

}
