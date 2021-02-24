<?php

namespace Drupal\drupalauth4ssp\Exception;

use Drupal\drupalauth4ssp\Helper\StringHelpers;

/**
 * Indicates operation attempted is inconsistent with the program state.
 */
class InvalidOperationException extends \RuntimeException {

  /**
   * Creates a new InvalidOperationException object.
   *
   * @param string|null $message
   *   Message pertaining to exception; can be NULL or an empty string, in which
   *   case a default message is used.
   * @param int $code
   *   Exception code.
   * @param Throwable|null $previous
   *   Previous exception/error which triggered this exception. Can be NULL to
   *   indicate no such error.
   */
  public function __construct(?string $message = NULL, int $code = 0, ?\Throwable $previous = NULL) {
    // Call the parent constructor with the message (either $message, or, if
    // $message is null or empty, a default message) and other parameters.
    parent::__construct(StringHelpers::getValueOrDefault($message, 'The operation attempted is inconsistent with the current state.'), $code, $previous);
  }

}
