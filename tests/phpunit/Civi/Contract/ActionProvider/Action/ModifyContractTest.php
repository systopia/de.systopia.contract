<?php

declare(strict_types = 1);

namespace Systopia\Contract\Tests\Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Parameter\ParameterBag;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\Api4\Campaign;
use Civi\Api4\FinancialType;
use Civi\Api4\OptionValue;
use Civi\Api4\SepaCreditor;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\Contract\Tests\Support\DummyContract;
use Systopia\Contract\Tests\Support\DummyModifyContract;
use Systopia\TestFixtures\Core\FixtureEntityStore;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionScenario;

/**
 * @covers \Civi\Contract\ActionProvider\Action\ModifyContract
 * @group headless
 */
final class ModifyContractTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    FixtureEntityStore::reset();

    return Test::headless()
      ->installMe(__DIR__)
      ->install('civi_campaign')
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->apply();
  }

  public function testGetConfigurationSpecification_LoadsAllExpectedSpecs(): void {
    $action = new DummyModifyContract();
    $specs = $action->getConfigurationSpecification();

    $actualNames = [];
    /**
     * @var \Civi\ActionProvider\Parameter\Specification $spec
     */
    foreach ($specs as $spec) {
      $actualNames[] = $spec->getName();
    }
    sort($actualNames);

    $expectedNames = [
      'default_action',
      'default_membership_type_id',
      'default_campaign_id',
      'default_payment_option',
      'default_payment_frequency',
      'default_cycle_day',
    ];
    sort($expectedNames);

    self::assertSame($expectedNames, $actualNames);
  }

  public function testGetParameterSpecification_LoadsAllExpectedSpecs(): void {
    $action = new DummyModifyContract();
    $specs = $action->getParameterSpecification();

    $actualNames = [];
    /**
     * @var \Civi\ActionProvider\Parameter\Specification $spec
     */
    foreach ($specs as $spec) {
      $actualNames[] = $spec->getName();
    }
    sort($actualNames);

    $expectedNames = [
      'id',
      'action',
      'medium_id',
      'note',
      'membership_type_id',
      'campaign_id',
      'payment_option',
      'payment_amount',
      'payment_frequency',
      'cycle_day',
      'iban',
      'bic',
      'account_holder',
      'defer_payment_start',
      'recurring_contribution',
      'cancel_reason',
      'resume_date',
    ];
    sort($expectedNames);

    self::assertSame($expectedNames, $actualNames);
  }

  public function testDoAction_WithDefaults_ModifiesContractSuccessfully(): void {
    $contractId = $this->createInitialContract();

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];

    $campaign = Campaign::create(FALSE)
      ->setValues([
        'title' => 'Modify Contract Test Campaign',
        'name' => 'modify_contract_test_campaign',
        'is_active' => TRUE,
      ])
      ->execute()
      ->single();

    $action = new DummyModifyContract();
    $action->setTestConfiguration([
      'default_action' => 'update',
      'default_membership_type_id' => $membership['membership_type_id'],
      'default_campaign_id' => $campaign['id'],
      'default_payment_option' => 'nochange',
      'default_payment_frequency' => 12,
      'default_cycle_day' => 5,
    ]);

    $values = OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id:name', '=', 'encounter_medium')
      ->addWhere('is_active', '=', 1)
      ->addOrderBy('weight', 'ASC')
      ->execute()->first();

    self::assertNotNull($values);

    $parameters = $this->createParameterBag([
      'id' => $contractId,
      'note' => 'Updated through ModifyContract action provider test',
      'medium_id' => $values['value'],
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    self::assertSame($contractId, $output->getParameter('id'));
    self::assertEmpty((string) $output->getParameter('error'));

    /**
     * @var array<string, int> $contract
     */
    $contract = \civicrm_api3('Contract', 'getsingle', [
      'id' => $contractId,
      'return' => ['id'],
    ]);

    self::assertSame($contractId, (int) $contract['id']);
  }

  public function testDoAction_WithInvalidContractId_ReturnsError(): void {
    $storedEntities = FixtureEntityStore::getEntities();
    if (!isset($storedEntities['Civi\Api4\Membership'])) {
      ContributionScenario::contactWithMembershipAndOpenContribution();
      $storedEntities = FixtureEntityStore::getEntities();
    }

    $membership = $storedEntities['Civi\Api4\Membership'];

    $action = new DummyModifyContract();
    $action->setTestConfiguration([
      'default_action' => 'update',
      'default_membership_type_id' => $membership['membership_type_id'],
      'default_campaign_id' => NULL,
      'default_payment_option' => 'nochange',
      'default_payment_frequency' => 12,
      'default_cycle_day' => 5,
    ]);

    $parameters = $this->createParameterBag([
      'id' => 999999999,
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    self::assertSame('', $output->getParameter('id'));
    self::assertNotEmpty((string) $output->getParameter('error'));
  }

  public function testGetOutputSpecification_ReturnsExpectedSpecs(): void {
    $action = new DummyModifyContract();
    $output = $action->getOutputSpecification();

    self::assertNotNull($output);
    self::assertNotNull($output->getSpecificationByName('id'));
    self::assertNotNull($output->getSpecificationByName('error'));
    self::assertSame('Contract ID', $output->getSpecificationByName('id')->getTitle());
    self::assertSame('Error Message', $output->getSpecificationByName('error')->getTitle());
  }

  private function createInitialContract(): int {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);
    self::assertNotNull($result['contributionId']);

    $financialType = FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Donation')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($financialType);

    $creditor = SepaCreditor::get(FALSE)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($creditor);

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    $contact = $storedEntities['Civi\Api4\Contact'];

    $action = new DummyContract();
    $action->setTestConfiguration([
      'default_membership_type_id' => $membership['membership_type_id'],
      'default_creditor_id' => $creditor['id'],
      'default_financial_type_id' => $financialType['id'],
      'default_campaign_id' => NULL,
      'default_frequency' => 12,
      'default_cycle_day' => 5,
      'buffer_days' => 0,
      'prevent_multiple_contracts' => 0,
    ]);

    $parameters = $this->createParameterBag([
      'contact_id' => $contact['id'],
      'iban' => 'DE89370400440532013000',
      'bic' => 'COBADEFFXXX',
      'amount' => '12.34',
      'start_date' => '2026-03-10 00:00:00',
      'join_date' => '2026-03-10',
      'date' => '2026-03-10 00:00:00',
      'validation_date' => '2026-03-10 00:00:00',
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    $mandateId = $output->getParameter('mandate_id');
    $mandateReference = $output->getParameter('mandate_reference');
    $contractId = $output->getParameter('contract_id');
    $error = $output->getParameter('error');

    self::assertNotEmpty($mandateId);
    self::assertNotEmpty($mandateReference);
    self::assertNotEmpty($contractId);
    self::assertEmpty($error);

    return (int) $contractId;
  }

  /**
   * @param array<string, mixed> $values
   */
  private function createParameterBag(array $values): ParameterBagInterface {
    $bag = new ParameterBag();
    foreach ($values as $name => $value) {
      $bag->setParameter($name, $value);
    }

    return $bag;
  }

}
