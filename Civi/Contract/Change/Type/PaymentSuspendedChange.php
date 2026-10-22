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
use Civi\Contract\Change\ActionMenuAwareContractChangeTypeInterface;
use Civi\Contract\Change\ActionMenuEntry;
use CRM_Contract_ExtensionUtil as E;

class PaymentSuspendedChange extends AbstractContractChange implements ActionMenuAwareContractChangeTypeInterface {

  public static function getActivityTypeName(): string {
    return 'Contract_Payment_Suspended';
  }

  public static function getActivityTypeIcon(): ?string {
    return NULL;
  }

  public static function getTitle(): string {
    return E::ts('Payment Suspended');
  }

  /**
   * @inheritDoc
   */
  public function populateData(): void {
    parent::populateData();
    $contract = $this->getContract();
    $this->data['contract_payment_suspended.contribution_recur_id']
      = $contract['membership_payment.membership_recurring_contribution'];
  }

  /**
   * @inheritDoc
   */
  protected function renderSubject(?array $contractAfter, ?array $contractBefore): string {
    return self::getTitle();
  }

  /**
   * @inheritDoc
   */
  public static function getActionMenuEntry(): ActionMenuEntry {
    return new ActionMenuEntry(
      E::ts('Suspend Payment'),
      'civicrm/contract/suspend-payment?id=[id]'
    );
  }

  /**
   * @inheritDoc
   */
  public static function getStartStatusList(): array {
    return ['Current'];
  }

}
