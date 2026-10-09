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

namespace Civi\Contract\Api4\Action\Contract;

use Civi\Api4\Activity;
use Civi\Api4\Contract;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Membership;
use Civi\Api4\SepaMandate;
use Civi\Contract\Change\Type\PaymentReinstatedChange;
use Civi\Contract\DataFixture\ContractFixture;
use Civi\Contract\Support\AbstractSetupHeadless;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\MembershipTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Contract\Api4\Action\Contract\ReinstatePaymentAction
 *
 * @group headless
 */
final class ReinstatePaymentActionTest extends AbstractSetupHeadless {

  public function test(): void {
    $creditorId = SepaCreditorBuilder::createDefault();
    \CRM_Sepa_Logic_Settings::setSetting($creditorId, 'batching_default_creditor');
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId = MembershipTypeBuilder::create();

    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId);

    Contract::suspendPayment(FALSE)
      ->setId($contract['id'])
      ->setReason('Unknown')
      ->execute();

    static::assertEquals(
      [
        'id' => $contract['id'],
        'status_id:name' => 'Current',
      ],
      Contract::reinstatePayment()
        ->setId($contract['id'])
        ->setNotes('test')
        ->setReplaceSepaMandate(FALSE)
        ->setCollectOutstanding(TRUE)
        ->execute()
        ->single()
    );

    static::assertCount(1, Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', PaymentReinstatedChange::getActivityTypeName())
      ->execute()
    );
  }

  public function testReplaceMandate(): void {
    $creditorId = SepaCreditorBuilder::createDefault();
    \CRM_Sepa_Logic_Settings::setSetting($creditorId, 'batching_default_creditor');
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId = MembershipTypeBuilder::create();

    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId);

    Contract::suspendPayment(FALSE)
      ->setId($contract['id'])
      ->setReason('Unknown')
      ->execute();

    static::assertEquals(
      [
        'id' => $contract['id'],
        'status_id:name' => 'Current',
      ],
      Contract::reinstatePayment()
        ->setId($contract['id'])
        ->setNotes('test')
        ->setReplaceSepaMandate(TRUE)
        ->setCollectOutstanding(FALSE)
        ->setIban('DE07123412341234123412')
        ->setBic('TESTDEFFXXY')
        ->setAccountHolder('new')
        ->setCycleDay(22)
        ->setPaymentAmount(22.22)
        ->setPaymentFrequency(2)
        ->execute()
        ->single()
    );

    $membership = Membership::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $contract['id'])
      ->execute()
      ->single();

    $sepaMandate = SepaMandate::get(FALSE)
      ->addSelect('id', 'account_holder', 'iban', 'bic')
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addWhere('entity_id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->execute()
      ->single();

    static::assertSame('new', $sepaMandate['account_holder']);
    static::assertSame('DE07123412341234123412', $sepaMandate['iban']);
    static::assertSame('TESTDEFFXXY', $sepaMandate['bic']);

    $contributionRecur = ContributionRecur::get(FALSE)
      ->addSelect('amount', 'frequency_interval', 'cycle_day')
      ->addWhere('id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->execute()
      ->single();
    static::assertSame(22, $contributionRecur['cycle_day']);
    static::assertSame(22.22, $contributionRecur['amount']);
    // Twice a year => every 6 month.
    static::assertSame(6, $contributionRecur['frequency_interval']);

    static::assertCount(1, Activity::get(FALSE)
      ->addWhere('activity_type_id:name', '=', PaymentReinstatedChange::getActivityTypeName())
      ->execute()
    );
  }

}
