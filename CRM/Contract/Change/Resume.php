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
class CRM_Contract_Change_Resume extends CRM_Contract_Change {

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
    if ($payment_contract_id) {
      CRM_Contract_SepaLogic::resumeSepaMandate($payment_contract_id);
      $this->updateContract(['status_id' => 'Current']);
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
  public function renderDefaultSubject(?array $contract_after, ?array $contract_before = NULL): string {
    if ($this->isNew()) {
      return E::ts('Resume contract');
    }
    return E::ts('Contract resumed');
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['Paused'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts('Resume Contract');
  }

}
