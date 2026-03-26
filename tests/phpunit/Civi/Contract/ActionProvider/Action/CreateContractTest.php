<?php

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Parameter\ParameterBag;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\Api4\ContributionRecur;
use Civi\Api4\FinancialType;
use Civi\Api4\SepaCreditor;
use Civi\Api4\SepaMandate;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use Civi\Contract\Support\DummyContract;
use PHPUnit\Framework\TestCase;
use Systopia;
use Systopia\TestFixtures\Core\FixtureEntityStore;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionScenario;

/**
 * @covers \Civi\Contract\ActionProvider\Action\CreateContract
 * @group headless
 */
final class CreateContractTest extends TestCase implements HeadlessInterface, TransactionalInterface {

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
    $action = new CreateContract();
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
      'default_membership_type_id',
      'default_creditor_id',
      'default_financial_type_id',
      'default_campaign_id',
      'default_payment_option',
      'default_frequency',
      'default_cycle_day',
      'buffer_days',
      'prevent_multiple_contracts',
    ];
    sort($expectedNames);

    self::assertSame($expectedNames, $actualNames);
  }

  public function testGetParameterSpecification_LoadsAllExpectedSpecs(): void {
    $action = new CreateContract();
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
      'contact_id',
      'membership_type_id',
      'iban',
      'bic',
      'amount',
      'reference',
      'frequency',
      'cycle_day',
      'creditor_id',
      'financial_type_id',
      'campaign_id',
      'start_date',
      'join_date',
      'date',
      'validation_date',
      'account_holder',
      'payment_option',
    ];
    sort($expectedNames);

    self::assertSame($expectedNames, $actualNames);
  }

  public function testDoAction_WithDefaults_CreatesDefaultContract(): void {
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

    $mandate = SepaMandate::get(FALSE)
      ->addSelect(
        'id',
        'contact_id',
        'creditor_id',
        'financial_type_id',
        'cycle_day',
        'frequency_interval',
        'frequency_unit',
        'entity_id',
      )
      ->addWhere('id', '=', $mandateId)
      ->execute()
      ->single();

    /**
     * @var array<string, int> $contract
     */
    $contract = \civicrm_api3(
      'Contract',
      'getsingle',
      ['id' => $contractId, 'return' => ['contact_id', 'membership_type_id']]
    );

    self::assertSame($contact['id'], $mandate['contact_id']);
    self::assertSame($creditor['id'], $mandate['creditor_id']);

    self::assertSame($contact['id'], (int) $contract['contact_id']);
    self::assertSame($membership['membership_type_id'], (int) $contract['membership_type_id']);
  }

  public function testDoAction_WithoutCycleDayAndActiveMandate_ThrowsError(): void {
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

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    $contact = $storedEntities['Civi\Api4\Contact'];

    $action = new DummyContract();
    $action->setTestConfiguration([
      'default_membership_type_id' => $membership['membership_type_id'],
      'default_financial_type_id' => $financialType['id'],
      'default_campaign_id' => NULL,
      'default_frequency' => 12,
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

    $mandate = $output->getParameter('mandate_id');
    $mandateRef = $output->getParameter('mandate_reference');
    $contract = $output->getParameter('contract_id');
    $error = $output->getParameter('error');

    self::assertEquals('', $mandate);
    self::assertEquals('', $mandateRef);
    self::assertEquals('', $contract);
    self::assertEquals('FRST mandate for creditor ID [] disabled, i.e. no valid payment instrument set.', $error);
  }

  public function testDoAction_WithoutCycleDay_CreatesDefaultContract(): void {
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

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    $contact = $storedEntities['Civi\Api4\Contact'];

    $creditor = SepaCreditor::get(FALSE)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($creditor);

    \CRM_Sepa_Logic_Settings::setSetting($creditor['id'], 'batching_default_creditor');

    $action = new DummyContract();
    $action->setTestConfiguration([
      'default_membership_type_id' => $membership['membership_type_id'],
      'default_creditor_id' => $creditor['id'],
      'default_financial_type_id' => $financialType['id'],
      'default_campaign_id' => NULL,
      'default_frequency' => 12,
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

    $mandate = $output->getParameter('mandate_id');
    $mandateRef = $output->getParameter('mandate_reference');
    $contract = $output->getParameter('contract_id');
    $error = $output->getParameter('error');

    self::assertGreaterThan(0, $mandate);
    self::assertStringContainsString('RCUR', $mandateRef);
    self::assertGreaterThan(0, $contract);
    self::assertEquals('', $error);
  }

  public function testDoAction_WithOverrides_UsesProvidedValues(): void {
    $defaultBag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $defaultResult = $defaultBag->toArray();

    self::assertNotNull($defaultResult['contactId']);
    self::assertNotNull($defaultResult['membershipId']);
    self::assertNotNull($defaultResult['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $defaultMembership = $storedEntities['Civi\Api4\Membership'];

    $overrideBag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $overrideResult = $overrideBag->toArray();

    self::assertNotNull($overrideResult['contactId']);
    self::assertNotNull($overrideResult['membershipId']);
    self::assertNotNull($overrideResult['contributionId']);

    $storedEntities = FixtureEntityStore::getEntities();
    $overrideContact = $storedEntities['Civi\Api4\Contact'];
    $overrideMembership = $storedEntities['Civi\Api4\Membership'];

    // Defaults

    $defaultFinancialType = FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Donation')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($defaultFinancialType);

    $defaultCreditor = SepaCreditor::get(FALSE)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($defaultCreditor);

    // Overrides

    $overrideFinancialType = FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Member Dues')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($overrideFinancialType);

    $overrideCreditor = Systopia\TestFixtures\Fixtures\Builders\SepaCreditorBuilder::create();
    self::assertGreaterThan(0, $overrideCreditor);

    $action = new DummyContract();
    $action->setTestConfiguration([
      'default_membership_type_id' => $defaultMembership['membership_type_id'],
      'default_creditor_id' => $defaultCreditor['id'],
      'default_financial_type_id' => $defaultFinancialType['id'],
      'default_campaign_id' => NULL,
      'default_frequency' => 12,
      'default_cycle_day' => 5,
      'buffer_days' => 0,
      'prevent_multiple_contracts' => 0,
    ]);

    $parameters = $this->createParameterBag([
      'contact_id' => $overrideContact['id'],
      'membership_type_id' => $overrideMembership['membership_type_id'],
      'iban' => 'DE89370400440532013000',
      'bic' => 'COBADEFFXXX',
      'amount' => '12.34',
      'creditor_id' => $overrideCreditor,
      'financial_type_id' => $overrideFinancialType['id'],
      'frequency' => 4,
      'cycle_day' => 12,
      'start_date' => '2026-03-10',
      'join_date' => '2026-02-15',
      'date' => '2026-03-10',
      'validation_date' => '2026-03-10',
      'account_holder' => 'Max Mustermann',
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    $mandateId = $output->getParameter('mandate_id');
    $contractId = $output->getParameter('contract_id');
    $error = $output->getParameter('error');

    self::assertNotEmpty($mandateId);
    self::assertNotEmpty($contractId);
    self::assertEmpty($error);

    $mandate = SepaMandate::get(FALSE)
      ->addSelect('entity_id', 'contact_id', 'creditor_id')
      ->addWhere('id', '=', $mandateId)
      ->execute()
      ->single();

    self::assertNotNull($mandate);

    $recur = ContributionRecur::get(FALSE)
      ->addWhere('id', '=', $mandate['entity_id'])
      ->execute()
      ->single();

    self::assertNotNull($recur);

    /**
     * @var array<string, int|string> $contract
     */
    $contract = \civicrm_api3(
      'Contract',
      'getsingle',
      ['id' => $contractId, 'return' => ['id', 'contact_id', 'membership_type_id', 'join_date']]
    );

    self::assertSame($overrideContact['id'], $mandate['contact_id']);
    self::assertSame($overrideCreditor, $mandate['creditor_id']);
    self::assertSame($overrideFinancialType['id'], $recur['financial_type_id']);
    self::assertSame(12, $recur['cycle_day']);
    self::assertSame(3, $recur['frequency_interval']);
    self::assertSame('month', $recur['frequency_unit']);

    self::assertSame($overrideContact['id'], (int) $contract['contact_id']);
    self::assertSame($overrideMembership['membership_type_id'], (int) $contract['membership_type_id']);
    self::assertSame('2026-02-15', substr((string) $contract['join_date'], 0, 10));
  }

  public function testDoAction_WithCashPayment_ReturnsCashContract(): void {
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

    $action = new DummyContract();

    $action->setTestConfiguration([
      'payment_option' => NULL,
    ]);

    $parameters = $this->createParameterBag([
      'contact_id' => $result['contactId'],
      'membership_type_id' => $membership['membership_type_id'],
      'amount' => '12.34',
      'financial_type_id' => $financialType['id'],
      'frequency' => 4,
      'cycle_day' => 12,
      'start_date' => '2026-03-10',
      'join_date' => '2026-02-15',
      'date' => '2026-03-10',
      'payment_option' => 'Cash',
      'currency' => 'EUR',
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    self::assertGreaterThan(0, $output->getParameter('contract_id'));
  }

  public function testDoAction_WithInvalidPayment_ThrowsException(): void {
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

    $creditor = SepaCreditor::get(FALSE)
      ->addSelect('id')
      ->setLimit(1)
      ->execute()
      ->first();

    self::assertNotNull($creditor);

    $action = new DummyContract();

    $action->setTestConfiguration([
      'default_creditor_id' => $creditor['id'],
      'payment_option' => NULL,
    ]);

    $parameters = $this->createParameterBag([
      'contact_id' => $result['contactId'],
      'membership_type_id' => $membership['membership_type_id'],
      'amount' => '12.34',
      'financial_type_id' => $financialType['id'],
      'frequency' => 4,
      'cycle_day' => 12,
      'start_date' => '2026-03-10',
      'join_date' => '2026-02-15',
      'date' => '2026-03-10',
      'payment_option' => 'Pikachu',
      'currency' => 'EUR',
    ]);

    $output = new ParameterBag();
    $action->runDoAction($parameters, $output);

    self::assertGreaterThan(0, $output->getParameter('contract_id'));
  }

  public function testGetOutputSpecification_ReturnsSpecificationBag(): void {
    $action = new DummyContract();
    $output = $action->getOutputSpecification();

    self::assertNotNull($output->getSpecificationByName('mandate_id'));
    self::assertNotNull($output->getSpecificationByName('mandate_reference'));
    self::assertNotNull($output->getSpecificationByName('contract_id'));
    self::assertNotNull($output->getSpecificationByName('error'));

    $mandate = $output->getSpecificationByName('mandate_id')->getTitle();
    $mandateRef = $output->getSpecificationByName('mandate_reference')->getTitle();
    $contract = $output->getSpecificationByName('contract_id')->getTitle();
    $error = $output->getSpecificationByName('error')->getTitle();

    self::assertEquals('Mandate ID', $mandate);
    self::assertEquals('Mandate Reference', $mandateRef);
    self::assertEquals('Contract ID', $contract);
    self::assertEquals('Error Message (if creation failed)', $error);
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
