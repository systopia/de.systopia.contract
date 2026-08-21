<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Contract\ContractChange\SchedulableContractChangeInterface;

/**
 * Base class for schedulable contract changes.
 */
// phpcs:ignore Generic.NamingConventions.AbstractClassNamePrefix.Missing, Generic.Files.LineLength.TooLong
abstract class CRM_Contract_SchedulableChange extends CRM_Contract_Change implements SchedulableContractChangeInterface {

  /**
   * @inheritDoc
   */
  public function shouldBeAccepted(): void {}

  /**
   * @inheritDoc
   */
  public function verifyData(): void {
    // simply check if all required fields are there
    // ...anything else needs to be checked in the specific class...
    $required_fields = $this->getRequiredFields();
    foreach ($required_fields as $required_field) {
      if (!isset($this->data[$required_field])) {
        throw new \RuntimeException("Parameter '{$required_field}' missing.");
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function verifyStatusChange(): void {
    $contract = $this->getContract();
    $status_name = CRM_Contract_Utils::getMembershipStatusName($contract['status_id']);
    if (!in_array($status_name, $this::getStartStatusList(), TRUE)) {
      throw new \RuntimeException("Cannot {$this::getActionName()} a membership when its status is '{$status_name}'.");
    }
  }

  public function checkForConflicts(): void {
    // TODO: refactor CRM_Contract_Handler_ModificationConflicts
    $conflictHandler = new CRM_Contract_Handler_ModificationConflicts();
    $conflictHandler->checkForConflicts($this->getContractID());
  }

}
