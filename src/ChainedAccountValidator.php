<?php

declare(strict_types = 1);

namespace Drupal\drupalauth4ssp;

use Drupal\Core\Session\AccountInterface;

/**
 * Represents a chain of other account validators.
 * 
 * If any of the validators returns 'FALSE,' validation is refused. Else,
 * validation is granted.
 */
class ChainedAccountValidator implements AccountValidatorInterface {

  /**
   * Array of sub-validators making up the validator chain.
   *
   * @var array
   */
  protected $subvalidators = [];

  /**
   * Adds a validator to the chain of validators.
   *
   * @param AccountValidatorInterface $validator
   *   Validator to add.
   * @return void
   */
  public function addValidator(AccountValidatorInterface $validator) : void {
    $this->subvalidators[] = $validator;
  }

  /**
   * @inheritdoc
   */
  public function isAccountValid(AccountInterface $account) : bool {
    foreach ($this->subvalidators as $validator) {
      if (!$validator->isAccountValid($account)) {
        return FALSE;
      }
    }
    return TRUE;
  }

}
