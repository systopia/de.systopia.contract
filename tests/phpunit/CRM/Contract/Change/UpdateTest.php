<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

/**
 * @group headless
 *
 * @covers \CRM_Contract_Change_Update
 */
class CRM_Contract_Change_UpdateTest extends CRM_Contract_ContractTestBase {

  public function testExecute_WithIncreaseAcrossThousands_StoresCorrectDiff(): void {
    $contract = $this->createNewContract(['is_sepa' => 1, 'amount' => '96.00']);

    $diff = $this->updateAnnualAmount($contract['id'], '60000.00');

    self::assertEqualsWithDelta(59904.00, $diff, 0.001);
  }

  public function testExecute_WithReductionAcrossThousands_StoresNegativeDiff(): void {
    $contract = $this->createNewContract(['is_sepa' => 1, 'amount' => '60000.00']);

    $diff = $this->updateAnnualAmount($contract['id'], '96.00');

    self::assertEqualsWithDelta(-59904.00, $diff, 0.001);
  }

  private function updateAnnualAmount(int $contractId, string $annual): float {
    $this->modifyContract($contractId, 'update', 'tomorrow', [
      'membership_payment.membership_annual' => $annual,
      'contract_updates.ch_payment_instrument' => CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR'),
    ]);
    $this->runContractEngine($contractId, '+2 days');

    $activity = $this->getLastChangeActivity($contractId, ['Contract_Updated']);

    return (float) $activity['contract_updates.ch_annual_diff'];
  }

}
