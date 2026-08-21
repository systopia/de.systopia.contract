<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * "Resume Membership" change
 */
class CRM_Contract_Change_Resume extends CRM_Contract_SchedulableChange {

  public static function getActionName(): string {
    return 'resume';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Resumed';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-play-circle-o';
  }

  public static function getTitle(): string {
    return E::ts('Resume Contract');
  }

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return list<string>
   */
  public function getRequiredFields(): array {
    return [];
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute(): void {
    $contract = $this->getContract(TRUE);

    // pause the mandate
    $payment_contract_id = $contract['membership_payment.membership_recurring_contribution'] ?? NULL;
    if (NULL !== $payment_contract_id) {
      CRM_Contract_SepaLogic::resumeSepaMandate($payment_contract_id);
      $this->updateContract(['status_id:name' => 'Current']);
    }

    // update change activity
    $contract_after = $this->getContract(TRUE);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    if ($this->isNew()) {
      return E::ts('Resume contract');
    }
    return E::ts('Contract resumed');
  }

  /**
   * @inheritDoc
   */
  public static function getStartStatusList(): array {
    return ['Paused'];
  }

}
