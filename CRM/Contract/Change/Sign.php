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
 * "New Membership Signed" record
 */
class CRM_Contract_Change_Sign extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return list<string>
   */
  public function getRequiredFields(): array {
    // none required because change is documentary
    return [];
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute(): void {
    throw new Exception(
      'New membership sign-ups are documentary, they cannot be scheduled into the future, and therefore not executed.'
    );
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    parent::populateData();
    $contract = $this->getContract(TRUE);
    $this->data['contract_updates.ch_annual_diff'] = CRM_Utils_Array::value(
      'membership_payment.membership_annual',
      $contract,
      ''
    );
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // TODO: check if the parameters are good
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    $c = (array) $contract_after;
    $parts = [];
    $type = isset($c['membership_type_id'])
      ? $this->labelValue($c['membership_type_id'], 'membership_type_id')
      : NULL;
    if ($type) {
      $parts[] = $type;
    }
    if (!empty($c['membership_payment.membership_frequency'])) {
      $freq = $this->labelValue($c['membership_payment.membership_frequency'], 'membership_payment.membership_frequency');
      if ($freq) {
        $parts[] = $freq;
      }
    }
    if (!empty($c['membership_payment.membership_annual'])) {
      $parts[] = E::ts('Annual %1', [1 => $c['membership_payment.membership_annual']]);
    }
    if (!empty($c['membership_payment.payment_instrument'])) {
      $pi = $this->labelValue($c['membership_payment.payment_instrument'], 'membership_payment.payment_instrument');
      if ($pi) {
        $parts[] = $pi;
      }
    }
    if (!empty($c['membership_payment.cycle_day'])) {
      $parts[] = E::ts('Cycle day %1', [1 => $c['membership_payment.cycle_day']]);
    }
    $suffix = $parts ? (' — ' . implode(' • ', $parts)) : '';
    return E::ts('New membership contract') . $suffix;
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts('Sign Contract');
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return [];
  }

}
