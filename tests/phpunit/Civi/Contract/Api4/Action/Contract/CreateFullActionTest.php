<?php

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\Api4\FinancialType;
use Civi\Api4\OptionValue;
use Civi\Contract\Support\AbstractSetupHeadless;
use Civi\Contract\Support\DummyCreateFullAction;
use Systopia\TestFixtures\Core\FixtureEntityStore;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionScenario;

/**
 * @covers \Civi\Contract\Api4\Action\Contract\CreateFullAction
 * @group headless
 */
final class CreateFullActionTest extends AbstractSetupHeadless {

  public function testCreateFull_WithCashPayment_CreatesContractAndRecurringContribution(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];

    $financialType = FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Donation')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($financialType);

    $cash = OptionValue::get(FALSE)
      ->addSelect('label')
      ->addWhere('option_group_id.name', '=', 'payment_instrument')
      ->addWhere('label', '=', 'Cash')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($cash);

    $creditorId = \Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder::create();
    self::assertGreaterThan(0, $creditorId);

    $action = new DummyCreateFullAction();

    $joinDate = date('Y-m-d', strtotime('-7 days'));

    $contract = $action->runWriteRecord([
      'contact_id' => $result['contactId'],
      'membership_type_id' => $membership['membership_type_id'],
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

    self::assertArrayHasKey('id', $contract);

    /**
     * @var array<string, int|string> $loadedContract
     */
    $loadedContract = civicrm_api3('Contract', 'getsingle', [
      'id' => $contract['id'],
      'return' => [
        'id',
        'contact_id',
        'membership_type_id',
        'join_date',
      ],
    ]);

    /**
     * @var string $joinDate
     */
    $joinDate = $loadedContract['join_date'];

    self::assertSame($result['contactId'], (int) $loadedContract['contact_id']);
    self::assertSame($membership['membership_type_id'], (int) $loadedContract['membership_type_id']);
    self::assertSame($joinDate, substr($joinDate, 0, 10));
  }

  public function testValidateValues_RejectsInvalidSepaWithoutIban(): void {
    self::markTestIncomplete('Validation per payment option is not implemented yet.');
  }

}
