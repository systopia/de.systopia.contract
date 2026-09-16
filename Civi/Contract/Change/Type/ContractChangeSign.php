<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Contract\Change\Type;

use Civi\Contract\Change\AbstractContractChange;
use CRM_Contract_ExtensionUtil as E;

/**
 * "New Membership Signed" record
 */
class ContractChangeSign extends AbstractContractChange {

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
      if ('' !== $freq) {
        $parts[] = $freq;
      }
    }
    if (!empty($c['membership_payment.membership_annual'])) {
      $parts[] = E::ts('Annual %1', [1 => $c['membership_payment.membership_annual']]);
    }
    if (!empty($c['membership_payment.payment_instrument'])) {
      $pi = $this->labelValue($c['membership_payment.payment_instrument'], 'membership_payment.payment_instrument');
      if ('' !== $pi) {
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
