<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp\Exception;

use Drupal\drupalauth4ssp\Helper\StringHelpers;

/**
 * Exception type indicating an internal simpleSAMLphp error.
 * 
 * Could indicate a simpleSAMLphp mis-configuration, or something else. If an
 * inner exception is available, it can be accessed with the getInnerException()
 * method.
 */
class SimpleSamlPhpInternalException extends \Exception {

  /**
   * Creates a new SimpleSamlPhpInternalException object.
   *
   * @param string|NULL $message Message pertaining to exception; can be null or
   *   an empty string, in which case a default message is used.
   * @param int $code Exception code.
   * @param Throwable|NULL $previous Previous exception/error which triggered
   * this exception. Can be NULL to indicate no such error.
   */
  public function __construct(?string $message = NULL, int $code = 0, ?\Throwable $previous = NULL) {
    $this->innerException = $innerException;
    // Call the parent constructor with the message (either $message, or, if
    // $message is null or empty, a default message).
    parent::__construct(StringHelpers::getValueOrDefault($message, 'An internal simpleSAMLphp error occurred.'), $code, $previous);
  }
  
}
