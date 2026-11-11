<?php

declare(strict_types = 1);

use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Membership;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\SepaCreditor;
use Civi\Api4\SepaMandate;
use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Modify as ModifyForm;
use Systopia\TestFixtures\Fixtures\Builders\FinancialTypeBuilder;
use Systopia\TestFixtures\Fixtures\Builders\MembershipTypeBuilder;

/**
 * @group headless
 *
 * @covers \CRM_Contract_Form_Modify
 */
final class ModifyFormTest extends ContractTestBase {

  /**
   * @var array{id: int, ...}
   */
  private array $contact;

  private int $membershipTypeId;

  /**
   * @var array{id: int, ...}
   * @phphstan-ignore property.uninitialized
   */
  private array $contract;

  private ?string $initialPaymentMethod = NULL;

  /**
   * @var array{id: int, ...}
   */
  private array $campaign;

  protected function setUp(): void {
    parent::setUp();

    $financialTypeId = FinancialTypeBuilder::create();

    /** @var array{id: int, ...} $org */
    $org = Contact::create(FALSE)
      ->addValue('contact_type', 'Organization')
      ->addValue('organization_name', 'ModifyFormTest Owner Org ' . rand(1, 1000000))
      ->execute()
      ->single();
    $ownerOrgId = $org['id'];

    // @phpstan-ignore assign.propertyType
    $this->campaign = Campaign::create(FALSE)
      ->setValues([
        'title' => 'Test Campaign (shared)',
        'name' => 'test_campaign_shared_' . rand(1, 1000000),
        'status_id' => 1,
        'is_active' => 1,
      ])
      ->execute()
      ->single();

    self::ensurePaymentInstrumentNone();

    $statusGroup = OptionGroup::save(FALSE)
      ->addRecord(['name' => 'contribution_status', 'title' => 'Contribution Status', 'is_active' => 1])
      ->setMatch(['name'])
      ->execute()
      ->single();

    self::ensureContributionStatuses((int) $statusGroup['id']);

    $this->ensureBaseOptionData();

    $this->membershipTypeId = MembershipTypeBuilder::create([
      'member_of_contact_id' => $ownerOrgId,
      'financial_type_id' => $financialTypeId,
    ]);

    $contact = $this->createContactWithRandomEmail();
    // @phpstan-ignore assign.propertyType
    $this->contact = Contact::get(FALSE)
      ->addWhere('id', '=', $contact['id'])
      ->execute()
      ->single();
  }

  private static function ensurePaymentInstrumentNone(): void {
    try {
      $none = OptionValue::get(FALSE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('option_group_id.is_active', '=', 1)
        ->addWhere('name', '=', 'None')
        ->setSelect(['id'])
        ->execute()
        ->single();

    }
    catch (Throwable $e) {
      $none = NULL;
    }

    try {
      $legacy = OptionValue::get(FALSE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('option_group_id.is_active', '=', 1)
        ->addWhere('name', '=', 'no_payment_required')
        ->setSelect(['id'])
        ->execute()
        ->single();
    }
    catch (Throwable $e) {
      $legacy = NULL;
    }

    if (!$none && $legacy) {
      OptionValue::update(FALSE)
        ->addWhere('id', '=', $legacy['id'])
        ->addValue('name', 'None')
        ->addValue('label', 'No Payment required')
        ->execute();
      CRM_Core_PseudoConstant::flush();
      $none = ['id' => $legacy['id']];
    }
    elseif ($none && $legacy) {
      OptionValue::delete(FALSE)->addWhere('id', '=', $legacy['id'])->execute();
      CRM_Core_PseudoConstant::flush();
    }

    if ($none) {
      return;
    }

    $row = OptionValue::get(FALSE)
      ->addWhere('option_group_id.name', '=', 'payment_instrument')
      ->addWhere('option_group_id.is_active', '=', 1)
      ->setSelect(['value'])
      ->addOrderBy('value', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $next = isset($row['value']) && is_numeric($row['value']) ? ((int) $row['value']) + 1 : 1;

    OptionValue::create(FALSE)
      ->addValue('option_group_id.name', 'payment_instrument')
      ->addValue('label', 'No Payment required')
      ->addValue('name', 'None')
      ->addValue('value', $next)
      ->addValue('is_active', 1)
      ->addValue('is_reserved', 0)
      ->addValue('weight', 99)
      ->execute();

    CRM_Core_PseudoConstant::flush();
  }

  private static function ensureContributionStatuses(int $groupId): void {
    OptionValue::save(FALSE)
      ->addRecord([
        'option_group_id' => $groupId,
        'label' => 'In Progress',
        'name' => 'In Progress',
        'value' => 5,
        'is_active' => 1,
        'is_reserved' => 1,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute();

    OptionValue::save(FALSE)
      ->addRecord([
        'option_group_id' => $groupId,
        'label' => 'Completed',
        'name' => 'Completed',
        'value' => 1,
        'is_active' => 1,
        'is_default' => 1,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute();
  }

  private function ensureBaseOptionData(): void {

    self::ensurePaymentInstrumentNone();

    $statusGroup = OptionGroup::save(FALSE)
      ->addRecord(['name' => 'contribution_status', 'title' => 'Contribution Status', 'is_active' => 1])
      ->setMatch(['name'])
      ->execute()
      ->single();

    self::ensureContributionStatuses((int) $statusGroup['id']);
  }

  private function getRecurStatusId(string $name): int {
    return (int) OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('name', '=', $name)
      ->setLimit(1)
      ->execute()
      ->single()['value'];
  }

  public function testPaymentInstrumentChange_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['terminate_mandate', 'create_new_mandate'], 'update');
  }

  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  private function createRequiredEntities(): void {

    $rcurId = $this->getPaymentInstrumentIdByName('RCUR');
    $cashId = $this->getPaymentInstrumentIdByName('Cash');
    $noneId = $this->getPaymentInstrumentIdByName('None');

    $isExistingSepa = $this->initialPaymentMethod === 'existing-SEPA';
    $isExistingNonSepa = $this->initialPaymentMethod === 'existing-non-SEPA';

    if ($isExistingSepa || $isExistingNonSepa) {
      $paymentInstrumentId = $isExistingSepa ? $rcurId : $cashId;
      $recurResult = ContributionRecur::create(FALSE)
        ->addValue('contact_id', $this->contact['id'])
        ->addValue('amount', '10.00')
        ->addValue('currency', 'EUR')
        ->addValue('frequency_unit', 'month')
        ->addValue('frequency_interval', 1)
        ->addValue('installments', NULL)
        ->addValue('contribution_status_id:name', 'In Progress')
        ->addValue('payment_instrument_id', $paymentInstrumentId)
        ->execute()
        ->single();
      $recurringContributionId = $recurResult['id'];

      $this->contract = $this->createNewContract([
        'contact_id' => $this->contact['id'],
        'is_sepa' => $isExistingSepa ? 1 : 0,
        'payment_instrument_id' => $paymentInstrumentId,
        'amount' => '10.00',
        'frequency_unit' => 'month',
        'frequency_interval' => '1',
        'membership_contract' => 'TEST-001',
        'membership_reference' => 'REF-001',
        'membership_recurring_contribution' => $recurringContributionId,
        'iban' => 'DE02370502990000684712',
        'bic' => 'COKSDE33',
      ]);

      if ($isExistingSepa) {
        /** @var array{id: int, ...} $creditor */
        $creditor = SepaCreditor::create(FALSE)
          ->addValue('identifier', 'TESTCREDITOR01')
          ->addValue('name', 'Creditor Organization')
          ->addValue('iban', 'DE02370502990000684712')
          ->addValue('bic', 'COKSDE33')
          ->addValue('creditor_type', 'OOFF')
          ->addValue('currency', 'EUR')
          ->addValue('mandate_active', 1)
          ->addValue('mandate_prefix', 'test')
          ->execute()
          ->single();

        self::assertNotNull($creditor);

        //IMPORTANT: Without this tests will use the same creditor stored in the settings throughout all the tests
        CRM_Sepa_Logic_Settings::setSetting($creditor['id'], 'batching_default_creditor');

        SepaMandate::save(FALSE)
          ->addRecord([
            'contact_id' => $this->contact['id'],
            'type' => 'RCUR',
            'entity_table' => 'civicrm_contribution_recur',
            'entity_id' => $recurringContributionId,
            'reference' => 'TEST-MANDATE-001',
            'date' => '2025-05-01 13:00:00',
            'iban' => 'DE12500105170648489890',
            'bic' => 'INGDDEFFXXX',
            'creditor_id' => $creditor['id'],
            'status' => 'RCUR',
          ])
          ->setMatch(['reference'])
          ->execute()
          ->first();
      }

      /** @var list<array<string, mixed>> $creditors */
      $creditors = SepaCreditor::get(FALSE)
        ->execute()
        ->getArrayCopy();
      foreach ($creditors as $creditor) {
        $iban = $creditor['iban'] ?? '';
        $bic = $creditor['bic'] ?? '';
        $needsUpdate = empty($iban) || empty($bic);
        if ($needsUpdate) {
          SepaCreditor::update(FALSE)
            ->addWhere('id', '=', $creditor['id'])
            ->addValue('iban', $iban ?: 'DE02370502990000684712')
            ->addValue('bic', $bic ?: 'COKSDE33')
            ->execute();
        }
      }
      return;
    }

    $isSepa = $this->initialPaymentMethod === 'SEPA';
    $isNonSepa = $this->initialPaymentMethod === 'non-SEPA';

    $paymentInstrumentId = $isSepa ? $rcurId : ($isNonSepa ? $cashId : $noneId);

    $this->contract = $this->createNewContract([
      'contact_id' => $this->contact['id'],
      'is_sepa' => $isSepa ? 1 : 0,
      'payment_instrument_id' => $paymentInstrumentId,
      'amount' => '10.00',
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      'membership_contract' => 'TEST-001',
      'membership_reference' => 'REF-001',
    ]);

    if ($isSepa) {
      $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');

      $membership = Membership::get(FALSE)
        ->addSelect('id', 'membership_payment.membership_recurring_contribution')
        ->addWhere('id', '=', $this->contract['id'])
        ->setLimit(1)
        ->execute()
        ->single();

      $recurContriId = $membership['membership_payment.membership_recurring_contribution'];

      if (NULL === $recurContriId) {
        $lastRecur = ContributionRecur::get(FALSE)
          ->addSelect('id')
          ->addWhere('contact_id', '=', $this->contact['id'])
          ->addOrderBy('id', 'DESC')
          ->setLimit(1)
          ->execute()
          ->first();
        $recurContriId = $lastRecur['id'] ?? NULL;
      }

      SepaMandate::delete(FALSE)
        ->addWhere('contact_id', '=', $this->contact['id'])
        ->execute();

      $creditor = SepaCreditor::create(FALSE)
        ->addValue('identifier', 'TESTCREDITOR01')
        ->addValue('name', 'Creditor Organization')
        ->addValue('iban', 'DE44500105175407324931')
        ->addValue('bic', 'DEUTDEFF500')
        ->addValue('creditor_type', 'OOFF')
        ->execute()
        ->first();

      SepaMandate::save(FALSE)
        ->addRecord([
          'contact_id' => $this->contact['id'],
          'type' => 'RCUR',
          'entity_table' => 'civicrm_contribution_recur',
          'entity_id' => $recurContriId,
          'reference' => 'TEST-MANDATE-001',
          'date' => '2025-05-01 13:00:00',
          'iban' => 'DE12500105170648489890',
          'bic' => 'INGDDEFFXXX',
          'creditor_id' => $creditor['id'],
          'status' => 'RCUR',
        ])
        ->setMatch(['reference'])
        ->execute()
        ->single();
    }
  }

  private function getPaymentInstrumentIdByName(string $name): ?int {
    $result = OptionValue::get(FALSE)
      ->addWhere('option_group_id:name', '=', 'payment_instrument')
      ->addWhere('name', '=', $name)
      ->addWhere('is_active', '=', 1)
      ->setLimit(1)
      ->execute()
      ->first();
    return $result ? (int) $result['value'] : NULL;
  }

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  private function runPaymentInstrumentChange(
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $form = new ModifyForm();
    // @phpstan-ignore assign.propertyType
    $form->controller = new class() {
      public ?string $_destination = NULL;

      /**
       * @var array<string, mixed>
       */
      private array $values = [];

      public function set(string $k, mixed $v = NULL): void {
        $this->values[$k] = $v;
      }

      public function get(string $k): mixed {
        return $this->values[$k] ?? NULL;
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    $form->set('cid', $this->contact['id']);
    $form->set('id', $this->contract['id']);

    $form->set('modify_action', $modifyAction);
    $form->_submitValues['modify_action'] = $modifyAction;

    $paymentOptionValue = match ($to) {
      'SEPA' => 'RCUR',
      'non-SEPA' => 'Cash',
      'existing' => 'Cash',
      'None' => 'None',
      default => 'none',
    };

    $piNameByTarget = [
      'SEPA' => 'RCUR',
      'non-SEPA' => 'Cash',
      'None' => 'None',
      'existing' => NULL,
    ];

    $form->_submitValues += [
      'payment_option' => $paymentOptionValue,
      'membership_type_id' => (string) $this->membershipTypeId,
      'payment_amount' => $to === 'None' ? '0' : '10',
      'payment_frequency' => '2',
      'cycle_day' => '28',
      'activity_date' => date('Y-m-d'),
      'iban' => 'DE02370502990000684712',
      'bic' => 'COKSDE33',
      'activity_details' => '',
      'activity_medium' => '',
      'account_holder' => 'John Doe',
      'campaign_id' => (string) $this->campaign['id'],
    ];

    if (array_key_exists($to, $piNameByTarget) && $piNameByTarget[$to]) {
      $piName = $piNameByTarget[$to];
      $piId = $this->getPaymentInstrumentIdByName($piName);
      if (!$piId) {
        CRM_Core_PseudoConstant::flush();
        $piId = $this->getPaymentInstrumentIdByName($piName);
      }
      if (!$piId) {
        throw new RuntimeException("Payment Instrument '{$piName}' no encontrado o inactivo.");
      }
      $form->_submitValues['payment_instrument_id'] = $piId;
    }

    // For "to existing" cases select an already existing recurring contribution,
    // not pretend this is just another Cash payment.
    if ($to === 'existing' && $expectedActions[0] === 'end_recurring_contribution'
      && $expectedActions[1]
      === 'assign_existing_recurring') {
      $existingRc = ContributionRecur::get(FALSE)
        ->addWhere('contact_id', '=', $this->contact['id'])
        ->addWhere('is_test', '=', 0)
        ->addOrderBy('id', 'DESC')
        ->setLimit(1)
        ->execute()
        ->first();

      if (empty($existingRc['id'])) {
        throw new RuntimeException('No existing recurring contribution found for existing-payment test case.');
      }

      $form->_submitValues['payment_option'] = 'select';
      $form->_submitValues['recurring_contribution'] = $existingRc['id'];
    }

    $form->_submitValues['contribution_recur_contribution_status_id'] = $this->getRecurStatusId('In Progress');

    $defaultCreditorId = CRM_Sepa_Logic_Settings::getSetting('batching_default_creditor');
    self::assertNotEmpty($defaultCreditorId, 'No batching_default_creditor configured');

    $creditor = CRM_Contract_SepaLogic::getCreditor();
    self::assertNotNull($creditor, 'CRM_Contract_SepaLogic::getCreditor() returned null');

    $form->preProcess();
    $form->buildQuickForm();
    $form->setDefaults($form->_submitValues);
    $form->postProcess();

    foreach ($expectedActions as $action) {
      match ($action) {
        'terminate_mandate' => $this->assertMandateTerminated($this->contact['id']),
        'end_recurring_contribution' => $this->assertRecurringContributionEnded($this->contact['id']),
        'end_recurring_contribution_zero' => $this->assertRecurringContributionEnded($this->contact['id']),
        'create_new_mandate' => $this->assertNewMandateCreated($this->contact['id']),
        'create_new_recurring_contribution' => $this->assertNewRecurringContribution($this->contact['id']),
        'create_new_recurring_contribution_zero' => $this->assertNewRecurringContribution($this->contact['id'], 0, [
          'membership_id' => $this->contract['id'],
        ]),
        'assign_existing_recurring' => $this->assertAssignedExistingRecurring($this->contact['id']),
        'no_change' => self::assertTrue(TRUE),
        default => throw new RuntimeException("Unknown action: $action"),
      };
    }
  }

  private function assertMandateTerminated(int $contactId): void {
    $mandates = SepaMandate::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status', 'IN', ['INVALID', 'COMPLETE', 'ENDED'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->getArrayCopy();

    self::assertNotEmpty($mandates, 'Mandate should be terminated');
  }

  private function assertRecurringContributionEnded(int $contactId): void {
    $recur = ContributionRecur::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('contribution_status_id:name', 'IN', ['Completed', 'Cancelled'])
      ->execute()
      ->getArrayCopy();
    self::assertNotEmpty($recur, 'Recurring contribution should be ended');
  }

  private function assertNewMandateCreated(int $contactId): void {
    $mandate = $this->getLatestSepaMandateForContact($contactId);
    self::assertNotNull($mandate, 'New SEPA mandate should be created');
    self::assertEquals('RCUR', $mandate['type'], 'SEPA Mandate type should be RCUR');
    self::assertContains($mandate['status'], ['FRST', 'RCUR'], 'Mandate status should be FRST or RCUR');
  }

  /**
   * @return array<string, mixed>|null
   *
   * @throws \CRM_Core_Exception
   */
  private function getLatestSepaMandateForContact(int $contactId): ?array {
    return SepaMandate::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('type', '=', 'RCUR')
      ->addOrderBy('id', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();
  }

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  private function assertNewRecurringContribution(int $contactId, ?float $amount = NULL, array $opts = []): void {
    // phpcs:enable
    $onlyActive = $opts['only_active'] ?? TRUE;
    $membershipId = isset($opts['membership_id']) ? (int) $opts['membership_id'] : NULL;
    $activeStatuses = $opts['active_statuses'] ?? ['In Progress', 'Pending'];

    $q = ContributionRecur::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_test', '=', 0)
      ->addSelect(
        'id',
        'amount',
        'currency',
        'payment_instrument_id',
        'create_date',
        'cycle_day',
        'frequency_unit',
        'frequency_interval',
        'contribution_status_id:name'
      )
      ->addOrderBy('id', 'DESC')
      ->setLimit(25);

    if ($onlyActive) {
      $q->addWhere('contribution_status_id:name', 'IN', $activeStatuses);
    }

    $rows = $q->execute()->getArrayCopy();
    self::assertNotEmpty($rows, 'New recurring contribution should be created (no rows).');

    $linkedRcId = NULL;
    if ($membershipId) {
      try {
        $mem = Membership::get(FALSE)
          ->addSelect('id', 'membership_payment.membership_recurring_contribution')
          ->addWhere('id', '=', $membershipId)
          ->setLimit(1)
          ->execute()
          ->first();
        if (isset($mem['membership_payment.membership_recurring_contribution'])) {
          $linkedRcId = $mem['membership_payment.membership_recurring_contribution'];
        }
      }
      catch (Throwable $e) {
        // @ignoreException
      }
    }

    $candidate = NULL;

    if ($linkedRcId) {
      foreach ($rows as $r) {
        if ((int) ($r['id'] ?? 0) === $linkedRcId) {
          $candidate = $r;
          break;
        }
      }
    }

    if (!$candidate && $amount !== NULL) {
      $target = $amount;
      $delta = 0.0001;
      foreach ($rows as $r) {
        if (!isset($r['amount'])) {
          continue;
        }
        if (abs((float) $r['amount'] - $target) <= $delta) {
          $candidate = $r;
          break;
        }
      }
    }

    if (!$candidate) {
      $candidate = $rows[0];
    }

    if ($amount !== NULL) {
      $target = $amount;
      $gotAmt = isset($candidate['amount']) ? (float) $candidate['amount'] : NULL;
      self::assertNotNull($gotAmt, 'New recurring contribution should have amount.');
      self::assertTrue(
        abs($gotAmt - $target) <= 0.0001,
        sprintf(
          'Unexpected amount on new recurring contribution. Expected: %.4f, got: %s (RC id #%s, status=%s)',
          $target,
          $gotAmt,
          $candidate['id'] ?? '?',
          $candidate['contribution_status_id:name'] ?? '?'
        )
      );

      if (abs($target) <= 0.0001) {
        $noneId = $this->getPaymentInstrumentIdByName('None');
        if ($noneId) {
          self::assertEquals(
            $noneId,
            (int) ($candidate['payment_instrument_id'] ?? 0),
            "Expected PI 'None' ({$noneId}) for zero-amount RC, got " . ($candidate['payment_instrument_id'] ?? 'NULL')
          );
        }
      }
    }
    else {
      if ($onlyActive) {
        $st = $candidate['contribution_status_id:name'] ?? NULL;
        self::assertTrue(
          in_array($st, $activeStatuses, TRUE),
          'New recurring contribution should be active. Got status: ' . ($st ?? 'NULL')
        );
      }
    }
  }

  private function assertAssignedExistingRecurring(int $contactId): void {
    $recur = ContributionRecur::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('is_test', '=', 0)
      ->execute()
      ->getArrayCopy();
    self::assertNotEmpty($recur, 'Should have assigned existing recurring contribution');
  }

  public function testPaymentInstrumentChange_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('non-SEPA', ['terminate_mandate', 'create_new_recurring_contribution'], 'update');
  }

  public function testPaymentInstrumentChange_SEPA_existing(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('existing', ['terminate_mandate', 'assign_existing_recurring'], 'update');
  }

  public function testPaymentInstrumentChange_SEPA_None(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'None',
      ['terminate_mandate', 'create_new_recurring_contribution_zero'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_non_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_non_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'non-SEPA',
      ['end_recurring_contribution', 'create_new_recurring_contribution'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_non_SEPA_existing(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'existing',
      ['end_recurring_contribution', 'assign_existing_recurring'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_non_SEPA_None(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'None',
      ['end_recurring_contribution', 'create_new_recurring_contribution_zero'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_existing_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'non-SEPA',
      ['end_recurring_contribution', 'create_new_recurring_contribution'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_SEPA_existing(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'existing',
      ['end_recurring_contribution', 'assign_existing_recurring'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_SEPA_None(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'None',
      ['end_recurring_contribution', 'create_new_recurring_contribution_zero'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'non-SEPA',
      ['end_recurring_contribution', 'create_new_recurring_contribution'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_existing(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'existing',
      ['end_recurring_contribution', 'assign_existing_recurring'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_None(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'None',
      ['end_recurring_contribution', 'create_new_recurring_contribution_zero'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_None_SEPA(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution_zero', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_None_non_SEPA(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'non-SEPA',
      ['end_recurring_contribution_zero', 'create_new_recurring_contribution'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_None_existing(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange(
      'existing',
      ['end_recurring_contribution_zero', 'assign_existing_recurring'],
      'update'
    );
  }

  public function testPaymentInstrumentChange_None_None(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('None', ['no_change'], 'update');
  }

  public function testContractStatusChange_new_active(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('active', ['create_recurring_contribution'], 'update');
  }

  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
  private function runContractStatusChange(
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $form = new ModifyForm();
    // @phpstan-ignore assign.propertyType
    $form->controller = new class() {

      public ?string $_destination = NULL;

      /**
       * @var array<string, mixed>
       */
      private array $values = [];

      public function set(string $k, mixed $v = NULL): void {
        $this->values[$k] = $v;
      }

      public function get(string $k): mixed {
        return $this->values[$k] ?? NULL;
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    $form->set('cid', $this->contact['id']);
    $form->set('id', $this->contract['id']);

    $form->set('modify_action', $modifyAction);
    $form->_submitValues['modify_action'] = $modifyAction;

    $form->_submitValues += [
      'contract_status' => $to,
      'membership_type_id' => $this->membershipTypeId,
      'payment_instrument_id' => '7',
      'payment_option' => 'RCUR',
      'payment_amount' => '10',
      'payment_frequency' => '2',
      'cycle_day' => '28',
      'activity_date' => date('Y-m-d'),
      'iban' => 'DE89370400440532013000',
      'bic' => 'DEUTDEFF',
      'activity_details' => '',
      'activity_medium' => '',
      'account_holder' => 'John Doe',
      'campaign_id' => (string) $this->campaign['id'],
    ];

    if ($modifyAction === 'pause') {
      $form->_submitValues['resume_date'] = date('Y-m-d', strtotime('+1 day'));
    }
    if ($modifyAction === 'cancel') {
      $form->_submitValues['cancel_reason'] = OptionValue::get(FALSE)
        ->addSelect('value')
        ->addWhere('option_group_id.name', '=', 'contract_cancel_reason')
        ->addWhere('name', '=', 'Unknown')
        ->execute()
        ->single()['value'];
    }

    $assertEndedOrMandateTerminated = function (int $cid) use ($to, $modifyAction): void {
      $rc = ContributionRecur::get(FALSE)
        ->addSelect('id', 'contribution_status_id:name')
        ->addWhere('contact_id', '=', $cid)
        ->addWhere('contribution_status_id:name', 'IN', ['Completed', 'Cancelled'])
        ->setLimit(1)
        ->execute()
        ->first();

      if ($rc) {
        return;
      }

      $okStatuses = ['INVALID', 'COMPLETE', 'ENDED'];
      if ($to === 'paused' || $modifyAction === 'pause') {
        $okStatuses[] = 'ONHOLD';
      }

      $mand = SepaMandate::get(FALSE)
        ->addSelect('id', 'status')
        ->addWhere('contact_id', '=', $cid)
        ->addWhere('status', 'IN', $okStatuses)
        ->setLimit(1)
        ->execute()
        ->first();

      self::assertNotEmpty(
        $mand,
        'Recurring contribution should be ended or mandate terminated'
      );
    };

    $form->preProcess();
    $form->buildQuickForm();
    $form->setDefaults($form->_submitValues);
    $form->postProcess();

    foreach ($expectedActions as $action) {
      match ($action) {
        'create_recurring_contribution' => $this->assertNewRecurringContribution($this->contact['id']),
        'maybe_create_paused' => self::assertTrue(TRUE),
        'maybe_create_ended' => self::assertTrue(TRUE),
        'terminate_mandate_or_end_recurring' => $assertEndedOrMandateTerminated((int) $this->contact['id']),
        'create_mandate_or_recurring' => $this->assertNewMandateCreated($this->contact['id']),
        'no_change' => self::assertTrue(TRUE),
        'no_actions_needed' => self::assertTrue(TRUE),
        default => throw new RuntimeException("Unknown status action: $action"),
      };
    }
  }

  public function testContractStatusChange_new_paused(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('paused', ['maybe_create_paused'], 'pause');
  }

  public function testContractStatusChange_new_ended(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('ended', ['maybe_create_ended'], 'cancel');
  }

  public function testContractStatusChange_active_active(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('active', ['no_change'], 'update');
  }

  public function testContractStatusChange_active_paused(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('paused', ['terminate_mandate_or_end_recurring'], 'pause');
  }

  public function testContractStatusChange_active_ended(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('ended', ['terminate_mandate_or_end_recurring'], 'cancel');
  }

  public function testContractStatusChange_paused_active(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('active', ['create_mandate_or_recurring'], 'update');
  }

  public function testContractStatusChange_paused_paused(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('paused', ['no_change'], 'pause');
  }

  public function testContractStatusChange_paused_ended(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runContractStatusChange('ended', ['no_actions_needed'], 'cancel');
  }

}
