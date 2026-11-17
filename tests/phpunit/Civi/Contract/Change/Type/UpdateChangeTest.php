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

use Civi\Contract\Event\ContractChangedEvent;

/**
 * @group headless
 *
 * @covers \Civi\Contract\Change\Type\UpdateChange
 * @covers \Civi\Contract\Change\AbstractContractChange
 * @covers \Civi\Contract\Event\ContractChangedEvent
 */
class UpdateChangeTest extends \CRM_Contract_ContractTestBase {

  public function testExecute_WithIncreaseAcrossThousands_StoresCorrectDiff(): void {
    $contract = $this->createNewContract(['is_sepa' => 1, 'amount' => 96.0]);

    // The action specific event listener should be called before the generic
    // one so $contractChangedEvent will be available there.
    $contractChangedEvent = NULL;
    $actionEventListenerCalls = 0;
    \Civi::dispatcher()->addListener(
      ContractChangedEvent::getActionEventName(UpdateChange::getActionName()),
      function (ContractChangedEvent $event) use(&$actionEventListenerCalls, &$contractChangedEvent, $contract) {
        $actionEventListenerCalls++;
        $contractChangedEvent = $event;
        static::assertSame($contract['id'], $event->membershipId);
        static::assertSame($contract['contact_id'], $event->contactId);
        static::assertSame(UpdateChange::getActionName(), $event->getActionName());
        static::assertSame(UpdateChange::getActivityTypeName(), $event->getActivityTypeName());
        static::assertSame(96.0, $event->getContractBefore()['membership_payment.membership_annual']);
        static::assertSame(60000.0, $event->getContractAfter()['membership_payment.membership_annual']);
      }
    );

    $eventListenerCalls = 0;
    \Civi::dispatcher()->addListener(
      ContractChangedEvent::class,
      function (ContractChangedEvent $event) use(&$eventListenerCalls, &$contractChangedEvent) {
        $eventListenerCalls++;
        static::assertSame($contractChangedEvent, $event);
      }
    );

    $diff = $this->updateAnnualAmount($contract['id'], 60000.0);

    self::assertEqualsWithDelta(59904.00, $diff, 0.001);
    static::assertSame(1, $eventListenerCalls);
    static::assertSame(1, $actionEventListenerCalls);
  }

  public function testExecute_WithReductionAcrossThousands_StoresNegativeDiff(): void {
    $contract = $this->createNewContract(['is_sepa' => 1, 'amount' => 60000.0]);

    $diff = $this->updateAnnualAmount($contract['id'], 96.0);

    self::assertEqualsWithDelta(-59904.00, $diff, 0.001);
  }

  private function updateAnnualAmount(int $contractId, float $annual): float {
    $this->modifyContract($contractId, 'update', 'tomorrow', [
      'membership_payment.membership_annual' => $annual,
      'contract_updates.ch_payment_instrument' => \CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR'),
    ]);
    $this->runContractEngine($contractId, '+2 days');

    $activity = $this->getLastChangeActivity($contractId, ['Contract_Updated']);

    return (float) $activity['contract_updates.ch_annual_diff'];
  }

}
