<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\user\UserInterface;

/**
 * Represents a chain of user validators.
 *
 * If any of the validators returns 'FALSE', validation is refused. Else,
 * validation is granted.
 */
class ChainedUserValidator implements UserValidatorInterface {

  /**
   * Array of sub-validators making up the validator chain.
   *
   * @var array
   */
  protected $subvalidators = [];

  /**
   * Adds a validator to the chain of validators.
   *
   * @param UserValidatorInterface $validator
   *   Validator to add.
   */
  public function addValidator(UserValidatorInterface $validator) : void {
    $this->subvalidators[] = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public function isUserValid(UserInterface $user) : bool {
    foreach ($this->subvalidators as $validator) {
      if (!$validator->isUserValid($user)) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
