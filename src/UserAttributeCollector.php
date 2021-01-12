<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\drupalauth4ssp\UserAttributeProcessorInterface;
use Drupal\user\UserInterface;

/**
 * Extracts user attributes that need to be returned to simpleSAMLphp.
 *
 * If this IdP is accessed in order to perform SP-initiated login, certain
 * attributes of the just-logged-in user should be sent back to the IdP. This
 * service is responsible for collecting and returning these attributes in a
 * form that can be processed by simpleSAMLphp.
 * Consumers of this service can add attributes to be collected by directly (or
 * indirectly, by adding a service implementing
 * @see Drupal\drupalauth4ssp\UserAttributeProcessorInterface and tagging this
 * service with the drupalauth4ssp.user_attribute_collector.attribute_processor
 * tag) calling the addAttributeProcessor() method.
 *
 * To obtain the processed attributes in a form that can be added to the
 * simpleSAMLphp $state array, call getAttributes() and use a foreach() loop
 * to iterate over the attributes.
 */
class UserAttributeCollector {

  /**
   * Attribute processors.
   *
   * @var array
   */
  protected $attributeProcessors = [];

  /**
   * Creates a new @see Drupal\drupalauth4ssp\UserAttributeCollector object.
   */
  public function __construct() {
  }

  /**
   * Adds an attribute processor to this collector.
   *
   * @param UserAttributeProcessorInterface $attributeProcessor
   *   Attribute processor to add.
   */
  public function addAttributeProcessor(UserAttributeProcessorInterface $attributeProcessor) : void {
    $attributeProcessors[] = $attributeProcessor;
  }

  /**
   * Gets attributes for a given user to the passed back to the SP.
   *
   * The simpleSAMLphp $state array can be updated using this method in the
   * following manner, for an object $collector of this class:
   *
   * foreach ($collector->getAttributes($user) as $attributeName => $attribute) {
   *   $state['Attributes'][$attributeName] = $attribute;
   * }
   *
   * @return Traversable
   *   Iterator which generates the attributes. Values in the iterator can be
   *   accessed with a foreach() loop.
   */
  public function getAttributes(UserInterface $user) : \Traversable {
    foreach ($attributeProcessors as $processor) {
      yield $processor->getAttributeName() => $procesor->getAttribute();
    }
  }

}
