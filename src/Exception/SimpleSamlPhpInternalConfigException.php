<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Exception;

use Drupal\drupalauth4ssp\Helper\StringHelpers;

/**
 * Exception type indicating an internal simpleSAMLphp configuration error.
 * 
 * Indicates a simpleSAMLphp mis-configuration error. If an inner exception is
 * available, it can be accessed with the getInnerException() method.
 */
class SimpleSamlPhpInternalConfigException extends SimpleSamlPhpInternalException {

  /**
   * Creates a new SimpleSamlPhpInternalConfigException object.
   *
   * @inheritdoc
   */
  public function __construct(?string $message = NULL, int $code = 0, ?\Throwable $previous = NULL) {
    // Call the parent constructor with the message (either $message, or, if
    // $message is null or empty, a default message) and other parameters.
    parent::__construct(StringHelpers::getValueOrDefault($message, 'An internal simpleSAMLphp configuration error occurred.'), $code, $previous);
  }

}
