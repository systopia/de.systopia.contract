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

use Civi\API\Exception\UnauthorizedException;
use Civi\Api4\Activity;
use Civi\Api4\Contract;
use Civi\Api4\Membership;
use Civi\Contract\Change\Type\UpdateChange;
use Civi\Contract\DataFixture\ContractFixture;
use Civi\Contract\Support\AbstractSetupHeadless;
use Systopia\TestFixtures\Fixtures\Builders\ContactBuilder;
use Systopia\TestFixtures\Fixtures\Builders\MembershipTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder;

/**
 * @covers \Civi\Contract\Api4\Action\Contract\ModifyAction
 * @covers \Civi\Api4\Contract
 */
final class ModifyActionTest extends AbstractSetupHeadless {

  protected function setUp(): void {
    parent::setUp();
    $creditorId = SepaCreditorBuilder::createDefault();
    \CRM_Sepa_Logic_Settings::setSetting($creditorId, 'batching_default_creditor');
  }

  public function test(): void {
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId1 = MembershipTypeBuilder::create();
    $membershipTypeId2 = MembershipTypeBuilder::create();
    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId1);

    $contract2 = Contract::modify()
      ->setId($contract['id'])
      ->setAction('update')
      ->setValues(['membership_type_id' => $membershipTypeId2])
      ->execute()
      ->single();

    // As of now $contract contains APIv3 values so we have to use assertEquals instead of assertSame.
    static::assertEquals($contract['id'], $contract2['id']);

    static::assertSame('Completed', Activity::get(FALSE)
      ->setSelect(['status_id:name'])
      ->addWhere('activity_type_id:name', '=', UpdateChange::getActivityTypeName())
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->execute()
      ->single()['status_id:name']
    );

    static::assertSame(
      $membershipTypeId2,
      Membership::get(FALSE)
        ->addSelect('membership_type_id')
        ->addWhere('id', '=', $contract['id'])
        ->execute()
        ->single()['membership_type_id']
    );
  }

  public function testDelayedExecution(): void {
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId1 = MembershipTypeBuilder::create();
    $membershipTypeId2 = MembershipTypeBuilder::create();
    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId1);

    $contract2 = Contract::modify()
      ->setId($contract['id'])
      ->setAction('update')
      ->setDate(date('Y-m-d H:i:s', strtotime('+1 day')))
      ->setValues(['membership_type_id' => $membershipTypeId2])
      ->execute()
      ->single();

    // As of now $contract contains APIv3 values so we have to use assertEquals instead of assertSame.
    static::assertEquals($contract['id'], $contract2['id']);

    static::assertSame('Scheduled', Activity::get(FALSE)
      ->setSelect(['status_id:name'])
      ->addWhere('activity_type_id:name', '=', UpdateChange::getActivityTypeName())
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->execute()
      ->single()['status_id:name']
    );

    static::assertSame(
      $membershipTypeId1,
      Membership::get(FALSE)
        ->addSelect('membership_type_id')
        ->addWhere('id', '=', $contract['id'])
        ->execute()
        ->single()['membership_type_id']
    );
  }

  public function testScheduledDateInPast(): void {
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId = MembershipTypeBuilder::create();
    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(
      "Parameter 'date' rejected because it's too far in the past, see the 'date_adjustment' setting."
    );
    Contract::modify()
      ->setId($contract['id'])
      ->setAction('update')
      ->setDate(date('Y-m-d H:i:s', strtotime('-1 day')))
      ->execute();
  }

  public function testPermissionMissing(): void {
    $contactId = ContactBuilder::createDefault();
    $membershipTypeId = MembershipTypeBuilder::create();
    $contract = ContractFixture::addSepaFixture($contactId, $membershipTypeId);

    $this->setUserPermissions(['access CiviCRM']);
    $this->expectException(UnauthorizedException::class);
    Contract::modify()
      ->setId($contract['id'])
      ->setAction('update')
      ->execute();
  }

}
