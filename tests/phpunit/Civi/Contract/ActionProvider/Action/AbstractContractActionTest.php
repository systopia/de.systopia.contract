<?php

declare(strict_types = 1);

namespace Systopia\Contract\Tests\Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Parameter\ParameterBag;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use PHPUnit\Framework\TestCase;
use Systopia\Contract\Tests\Support\DummyAbstractContractAction;
use Systopia\TestFixtures\Core\FixtureEntityStore;
use Systopia\TestFixtures\Fixtures\Scenarios\ContributionScenario;

/**
 * @covers \Civi\Contract\ActionProvider\Action\AbstractContractAction
 * @group headless
 */
final class AbstractContractActionTest extends TestCase implements HeadlessInterface, TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->install('civi_campaign')
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->apply();
  }

  public function testGetFrequencies_ReturnsExpectedOptions(): void {
    $action = new DummyAbstractContractAction();

    self::assertSame([
      1 => 'annually',
      2 => 'semi-annually',
      4 => 'quarterly',
      6 => 'bi-monthly',
      12 => 'monthly',
    ], $action->runGetFrequencies());
  }

  public function testGetCollectionDays_ReturnsExpectedOptions(): void {
    $action = new DummyAbstractContractAction();
    $days = $action->runGetCollectionDays();

    self::assertSame('as soon as possible', $days[0]);
    self::assertSame(1, $days[1]);
    self::assertSame(28, $days[28]);
    self::assertCount(29, $days);
  }

  public function testGetMembershipTypes_ReturnsCreatedMembershipTypes(): void {
    $bag = ContributionScenario::contactWithMembershipAndOpenContribution();
    $result = $bag->toArray();

    self::assertNotNull($result['contactId']);
    self::assertNotNull($result['membershipId']);

    $action = new DummyAbstractContractAction();
    $types = $action->runGetMembershipTypes();

    $storedEntities = FixtureEntityStore::getEntities();
    $membership = $storedEntities['Civi\Api4\Membership'];
    /**
     * @var int $membershipTypeId
     */
    $membershipTypeId = $membership['membership_type_id'];

    self::assertArrayHasKey($membershipTypeId, $types);
  }

  public function testGetCampaigns_ReturnsOnlyActiveCampaigns(): void {
    $activeCampaign = \Civi\Api4\Campaign::create(FALSE)
      ->setValues([
        'title' => 'AbstractContractActionTest Active Campaign',
        'name' => 'abstract_contract_action_active_' . uniqid(),
        'status_id' => 1,
        'is_active' => 1,
      ])
      ->execute()
      ->single();

    $inactiveCampaign = \Civi\Api4\Campaign::create(FALSE)
      ->setValues([
        'title' => 'AbstractContractActionTest Inactive Campaign',
        'name' => 'abstract_contract_action_inactive_' . uniqid(),
        'status_id' => 1,
        'is_active' => 0,
      ])
      ->execute()
      ->single();

    $action = new DummyAbstractContractAction();
    $campaigns = $action->runGetCampaigns();

    self::assertArrayHasKey($activeCampaign['id'], $campaigns);
    self::assertSame($activeCampaign['name'], $campaigns[$activeCampaign['id']]);
    self::assertArrayNotHasKey($inactiveCampaign['id'], $campaigns);
  }

  public function testTranslateParameterMap_WithParametersAndDefaults_ReturnsTranslatedValues(): void {
    $action = new DummyAbstractContractAction();
    $action->setTestConfiguration([
      'default_contact_id' => '42',
      'default_amount' => '9.99',
      'default_payment_option' => 'Cash',
    ]);

    $parameters = $this->createParameterBag([
      'amount' => '12.34',
      'payment_option' => 'RCUR',
    ]);

    $map = [
      'contact_id' => ['contact_id', 'default_contact_id', 'int'],
      'payment_amount' => ['amount', 'default_amount', 'float'],
      'payment_option' => ['payment_option', 'default_payment_option'],
    ];

    $result = $action->runTranslateParameterMap($map, $parameters);

    self::assertSame(42, $result['contact_id']);
    self::assertSame(12.34, $result['payment_amount']);
    self::assertSame('RCUR', $result['payment_option']);
  }

  public function testTranslateParameterMap_WithMissingValues_ReturnsNullOrCastFallbacks(): void {
    $action = new DummyAbstractContractAction();
    $action->setTestConfiguration([]);

    $parameters = $this->createParameterBag([]);

    $map = [
      'contact_id' => ['contact_id', 'default_contact_id', 'int'],
      'payment_amount' => ['amount', 'default_amount', 'float'],
      'payment_option' => ['payment_option', 'default_payment_option'],
    ];

    $result = $action->runTranslateParameterMap($map, $parameters);

    self::assertSame(0, $result['contact_id']);
    self::assertSame(0.0, $result['payment_amount']);
    self::assertNull($result['payment_option']);
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
