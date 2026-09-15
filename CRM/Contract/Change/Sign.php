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

  public static function getActivityTypeName(): string {
    return 'Contract_Signed';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-dot-circle-o';
  }

  public static function getTitle(): string {
    return E::ts('Sign Contract');
  }

  /**
   * Derive/populate additional data
   */
  public function populateData(): void {
    parent::populateData();
    $contract = $this->getContract(TRUE);
    $this->data['contract_updates.ch_annual_diff'] = $contract['membership_payment.membership_annual'] ?? 0.0;
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL):string {
    $c = (array) $contractAfter;
    $parts = [];
    $type = isset($c['membership_type_id'])
      ? $this->labelValue($c['membership_type_id'], 'membership_type_id')
      : NULL;
    if ($type) {
      $parts[] = $type;
    }
    if (!empty($c['membership_payment.membership_frequency'])) {
      $freq = $this->labelValue(
        $c['membership_payment.membership_frequency'],
        'membership_payment.membership_frequency'
      );
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
    $suffix = [] !== $parts ? (' — ' . implode(' • ', $parts)) : '';
    return E::ts('New membership contract') . $suffix;
  }

}
