<?php

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\Api4\FinancialType;
use Civi\Api4\SepaCreditor;
use Civi\Contract\Support\AbstractSetupHeadless;
use Civi\Contract\Support\DummyCreateFullAction;
use Civi\Contract\Support\DummyModifyFullAction;
use Systopia\TestFixtures\Core\FixtureEntityStore;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionRecurScenario;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionScenario;

/**
 * @covers \Civi\Contract\Api4\Action\Contract\ModifyFullAction
 * @group headless
 */
final class ModifyFullActionTest extends AbstractSetupHeadless {

  protected function setUp(): void {
    parent::setUp();

    FixtureEntityStore::reset();
    \CRM_Sepa_Logic_Settings::setSetting(NULL, 'batching_default_creditor');
  }

  public function testModifyFull_WithNoChange_UpdatesContractSuccessfully(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'update',
      'medium_id' => '',
      'note' => 'Modified by ModifyFullActionTest',
      'payment_option' => 'nochange',
      'membership_type_id' => $membership['membership_type_id'],
      'campaign_id' => '',
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Modified by ModifyFullActionTest', $activity['details']);
  }

  public function testModifyFull_WithSelect_UpdatesContractSuccessfully(): void {
    $bag = ContributionRecurScenario::pendingRecurWithCompletedContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'update',
      'medium_id' => '',
      'note' => 'Modified via select',
      'payment_option' => 'select',
      'recurring_contribution' => $result['recurringContributionId'],
      'membership_type_id' => $membership['membership_type_id'],
      'campaign_id' => '',
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Modified via select', $activity['details']);
  }

  public function testModifyFull_WithNone_UpdatesContractSuccessfully(): void {
    $bag = ContributionRecurScenario::pendingRecurWithCompletedContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'update',
      'medium_id' => '',
      'note' => 'Modified to None payment',
      'payment_option' => 'None',
      'membership_type_id' => $membership['membership_type_id'],
      'campaign_id' => '',
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Modified to None payment', $activity['details']);
  }

  public function testModifyFull_WithRcur_UpdatesContractSuccessfully(): void {

    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $defaultCreditor = SepaCreditor::get(FALSE)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($defaultCreditor);

    \CRM_Sepa_Logic_Settings::setSetting($defaultCreditor['id'], 'batching_default_creditor');

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'update',
      'medium_id' => '',
      'note' => 'Modified to RCUR payment',
      'payment_option' => 'RCUR',
      'payment_amount' => '18.50',
      'payment_frequency' => 12,
      'cycle_day' => 12,
      'iban' => 'DE89370400440532013000',
      'bic' => 'COBADEFFXXX',
      'account_holder' => 'Max Mustermann',
      'defer_payment_start' => '1',
      'membership_type_id' => $membership['membership_type_id'],
      'campaign_id' => '',
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Modified to RCUR payment', $activity['details']);
  }

  public function testModifyFull_WithCash_UpdatesContractSuccessfully(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'update',
      'medium_id' => '',
      'note' => 'Modified to Cash payment',
      'payment_option' => 'Cash',
      'payment_amount' => '18.50',
      'payment_frequency' => 12,
      'cycle_day' => 15,
      'iban' => 'DE89370400440532013000',
      'bic' => 'COBADEFFXXX',
      'account_holder' => 'Max Mustermann',
      'defer_payment_start' => '1',
      'membership_type_id' => $membership['membership_type_id'],
      'campaign_id' => '',
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Modified to Cash payment', $activity['details']);
  }

  public function testModifyFull_WithCancel_UpdatesContractSuccessfully(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);
    self::assertArrayHasKey('id', $contract);

    $customField = \Civi\Api4\CustomField::get(FALSE)
      ->addSelect('option_group_id')
      ->addWhere('name', '=', 'membership_cancel_reason')
      ->execute()
      ->single();

    self::assertNotNull($customField);

    $cancelReason = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('value', 'label', 'name')
      ->addWhere('option_group_id', '=', $customField['option_group_id'])
      ->addWhere('is_active', '=', 1)
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($cancelReason);

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'cancel',
      'medium_id' => '',
      'note' => 'Cancelled by test',
      'cancel_reason' => $cancelReason['name'],
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Cancelled by test', $activity['details']);
  }

  public function testModifyFull_WithPause_UpdatesContractSuccessfully(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    $contract = $this->createInitialContract($result['contactId'], $membershipTypeId);

    $resumeDate = date('Y-m-d', strtotime('+30 days'));

    $modifyAction = new DummyModifyFullAction();
    $modified = $modifyAction->runWriteRecord([
      'id' => $contract['id'],
      'action' => 'pause',
      'medium_id' => '',
      'note' => 'Paused by test',
      'resume_date' => $resumeDate,
    ]);

    self::assertArrayHasKey('id', $modified);

    $activity = \Civi\Api4\Activity::get(FALSE)
      ->addWhere('contract_activity.contract_id', '=', $contract['id'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    self::assertNotNull($activity);

    self::assertSame('Paused by test', $activity['details']);
  }

  public function testValidateValues_RejectsInvalidSepaWithoutIban(): void {
    self::markTestIncomplete('Validation per payment option is not implemented yet.');
  }

  /**
   * @param int $contactId
   * @param int $membershipTypeId
   * @return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function createInitialContract(int $contactId, int $membershipTypeId): array {
    $financialType = FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Donation')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($financialType);

    $joinDate = date('Y-m-d', strtotime('-7 days'));

    $createAction = new DummyCreateFullAction();
    $contract = $createAction->runWriteRecord([
      'contact_id' => $contactId,
      'membership_type_id' => $membershipTypeId,
      'join_date' => $joinDate,
      'start_date' => date('Y-m-d', strtotime('-7 days')),
      'end_date' => date('Y-m-d', strtotime('+1 year')),
      'financial_type_id' => $financialType['id'],
      'payment_amount' => '12.34',
      'payment_frequency' => 4,
      'cycle_day' => 12,
      'payment_option' => 'Cash',
      'account_holder' => 'Max Mustermann',
    ]);
    return $contract;
  }

}
