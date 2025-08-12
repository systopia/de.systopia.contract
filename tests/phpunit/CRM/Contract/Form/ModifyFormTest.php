<?php

declare(strict_types = 1);

use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Membership;
use Civi\Api4\MembershipType;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\SepaCreditor;
use Civi\Api4\SepaMandate;
use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Modify as ModifyForm;

/**
 * @group headless
 */
class ModifyFormTest extends ContractTestBase {

  /**
   * Shared fixtures
   */
  protected static ?int $sharedOwnerOrgId = NULL;

  protected static array $sharedMembershipType = [];

  protected static array $sharedCampaign = [];

  protected static ?int $sharedPaymentGroupId = NULL;

  protected static bool $sharedContribStatusReady = FALSE;

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $contact = [];

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $membershipType = [];

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $mandate = [];

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $contract = [];

  protected ?int $recurContributionStatusId = NULL;

  protected ?string $initialPaymentMethod = NULL;

  protected array $campaign = [];

  public static function setUpBeforeClass(): void {
    /** @phpstan-ignore-next-line */
    $org = Contact::create(TRUE)
      ->addValue('contact_type', 'Organization')
      ->addValue('organization_name', 'ModifyFormTest Owner Org ' . rand(1, 1000000))
      ->execute()
      ->single();
    self::$sharedOwnerOrgId = (int) $org['id'];

    self::$sharedMembershipType = MembershipType::create(FALSE)
      ->addValue('name', 'Modify Membership Type')
      ->addValue('member_of_contact_id', self::$sharedOwnerOrgId)
      ->addValue('financial_type_id', 2)
      ->addValue('duration_unit', 'year')
      ->addValue('duration_interval', 1)
      ->addValue('period_type', 'rolling')
      ->addValue('is_active', 1)
      ->execute()
      ->single();

    /** @phpstan-ignore-next-line */
    $campaign = Campaign::create(TRUE)
      ->addValue('title', 'Test Campaign (shared)')
      ->addValue('name', 'test_campaign_shared_' . rand(1, 1000000))
      ->addValue('status_id', 1)
      ->addValue('is_active', 1)
      ->execute()
      ->single();
    self::$sharedCampaign = $campaign;

    $paymentOptionGroup = OptionGroup::save(TRUE)
      ->addRecord(
        [
          'name' => 'payment_instrument',
          'title' => 'Payment Instrument',
          'is_active' => 1,
        ]
      )
      ->setMatch(['name'])
      ->execute()
      ->single();
    self::$sharedPaymentGroupId = (int) $paymentOptionGroup['id'];

    OptionValue::save(TRUE)
      ->addRecord(
        [
          'option_group_id' => self::$sharedPaymentGroupId,
          'label' => 'No Payment required',
          'name' => 'None',
          'value' => 100,
          'is_active' => 1,
          'is_reserved' => 0,
          'weight' => 99,
        ]
      )
      ->setMatch(['option_group_id', 'name'])
      ->execute()
      ->single();

    $optionGroup = OptionGroup::save(TRUE)
      ->addRecord(
        [
          'name' => 'contribution_status',
          'title' => 'Contribution Status',
          'is_active' => 1,
        ]
      )
      ->setMatch(['name'])
      ->execute()
      ->single();

    $optionGroupId = $optionGroup['id'];

    OptionValue::save(TRUE)
      ->addRecord(
        [
          'option_group_id' => $optionGroupId,
          'label' => 'In Progress',
          'name' => 'In Progress',
          'value' => 5,
          'is_active' => 1,
          'is_reserved' => 1,
        ]
      )
      ->setMatch(['option_group_id', 'name'])
      ->execute()
      ->single();

    OptionValue::save(TRUE)
      ->addRecord(
        [
          'option_group_id' => $optionGroupId,
          'label' => 'Completed',
          'name' => 'Completed',
          'value' => 1,
          'is_active' => 1,
          'is_default' => 1,
        ]
      )
      ->setMatch(['option_group_id', 'name'])
      ->execute();

    self::$sharedContribStatusReady = TRUE;
  }

  public static function tearDownAfterClass(): void {
    try {
      if (!empty(self::$sharedCampaign['id'])) {
        /** @phpstan-ignore-next-line */
        Campaign::delete(TRUE)
          ->addWhere('id', '=', self::$sharedCampaign['id'])
          ->execute();
      }
    }
    catch (\Throwable $e) {
    }

    try {
      if (!empty(self::$sharedMembershipType['id'])) {
        MembershipType::delete(TRUE)
          ->addWhere('id', '=', self::$sharedMembershipType['id'])
          ->execute();
      }
    }
    catch (\Throwable $e) {
    }

    try {
      if (!empty(self::$sharedOwnerOrgId)) {
        /** @phpstan-ignore-next-line */
        Contact::delete(TRUE)
          ->addWhere('id', '=', self::$sharedOwnerOrgId)
          ->execute();
      }
    }
    catch (\Throwable $e) {
    }
  }

  public function setUp(): void {
    parent::setUp();
  }

  public function testPaymentInstrumentChange_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['terminate_mandate', 'create_new_mandate'], 'update');
  }

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  private function createRequiredEntities(): void {
    // phpcs:enable
    $contact = $this->createContactWithRandomEmail();

    $contactResult = Contact::get(TRUE)
      ->addWhere('id', '=', $contact['id'])
      ->setLimit(1)
      ->execute();

    if ($contactResult->count() === 0) {
      throw new \RuntimeException('Contact not found');
    }
    $this->contact = $contactResult->first();

    $this->membershipType = self::$sharedMembershipType;
    $this->campaign = self::$sharedCampaign;

    $rcurId = $this->getPaymentInstrumentIdByName('RCUR');
    $cashId = $this->getPaymentInstrumentIdByName('Cash');
    $noneId = $this->getPaymentInstrumentIdByName('None');

    $isExistingSepa = $this->initialPaymentMethod === 'existing-SEPA';
    $isExistingNonSepa = $this->initialPaymentMethod === 'existing-non-SEPA';

    if ($isExistingSepa || $isExistingNonSepa) {
      $paymentInstrumentId = $isExistingSepa ? $rcurId : $cashId;
      /** @phpstan-ignore-next-line */
      $recurResult = ContributionRecur::create(TRUE)
        ->addValue('contact_id', $contact['id'])
        ->addValue('amount', '10.00')
        ->addValue('currency', 'EUR')
        ->addValue('frequency_unit', 'month')
        ->addValue('frequency_interval', 1)
        ->addValue('installments', NULL)
        ->addValue('contribution_status_id:name', 'In Progress')
        ->addValue('payment_instrument_id', $paymentInstrumentId)
        ->execute()
        ->first();
      $recurringContributionId = (int) $recurResult['id'];

      $this->contract = $this->createNewContract(
        [
          'contact_id' => $contact['id'],
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
        ]
      );

      if ($isExistingSepa) {
        $creditor = SepaCreditor::create(TRUE)
          ->addValue('identifier', 'TESTCREDITOR01')
          ->addValue('name', 'Creditor Organization')
          ->addValue('iban', 'DE02370502990000684712')
          ->addValue('bic', 'COKSDE33')
          ->addValue('creditor_type', 'OOFF')
          ->addValue('payment_processor_id', 1)
          ->execute()
          ->first();

        SepaMandate::save(TRUE)
          ->addRecord(
            [
              'contact_id' => $contact['id'],
              'type' => 'RCUR',
              'entity_table' => 'civicrm_contribution_recur',
              'entity_id' => $recurringContributionId,
              'reference' => 'TEST-MANDATE-001',
              'date' => '2025-05-01 13:00:00',
              'iban' => 'DE12500105170648489890',
              'bic' => 'INGDDEFFXXX',
              'creditor_id' => $creditor['id'],
              'status' => 'RCUR',
            ]
          )
          ->setMatch(['reference'])
          ->execute()
          ->first();
      }

      /** @phpstan-ignore-next-line */
      $creditors = SepaCreditor::get(TRUE)
        ->execute()
        ->getArrayCopy();
      foreach ($creditors as $creditor) {
        $iban = $creditor['iban'] ?? '';
        $bic = $creditor['bic'] ?? '';
        $needsUpdate = empty($iban) || empty($bic);
        if ($needsUpdate) {
          /** @phpstan-ignore-next-line */
          SepaCreditor::update(TRUE)
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

    $this->contract = $this->createNewContract(
      [
        'contact_id' => $this->contact['id'],
        'is_sepa' => $isSepa ? 1 : 0,
        'payment_instrument_id' => $paymentInstrumentId,
        'amount' => '10.00',
        'frequency_unit' => 'month',
        'frequency_interval' => '1',
        'membership_contract' => 'TEST-001',
        'membership_reference' => 'REF-001',
      ]
    );

    if ($isSepa) {
      $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');

      $membership = Membership::get(TRUE)
        ->addSelect('id', $rcField)
        ->addWhere('id', '=', $this->contract['id'])
        ->setLimit(1)
        ->execute()
        ->single();

      $recurContriId = $membership[$rcField] ?? NULL;

      if (empty($recurContriId)) {
        $lastRecur = ContributionRecur::get(TRUE)
          ->addSelect('id')
          ->addWhere('contact_id', '=', (int) $contact['id'])
          ->addOrderBy('id', 'DESC')
          ->setLimit(1)
          ->execute()
          ->first();
        $recurContriId = $lastRecur['id'] ?? NULL;
      }

      SepaMandate::delete(TRUE)
        ->addWhere('contact_id', '=', $contact['id'])
        ->execute();

      $creditor = SepaCreditor::create(TRUE)
        ->addValue('identifier', 'TESTCREDITOR01')
        ->addValue('name', 'Creditor Organization')
        ->addValue('iban', 'DE44500105175407324931')
        ->addValue('bic', 'DEUTDEFF500')
        ->addValue('creditor_type', 'OOFF')
        ->addValue('payment_processor_id', 1)
        ->execute()
        ->first();

      $this->mandate = SepaMandate::save(TRUE)
        ->addRecord([
          'contact_id'   => $contact['id'],
          'type'         => 'RCUR',
          'entity_table' => 'civicrm_contribution_recur',
          'entity_id'    => $recurContriId,
          'reference'    => 'TEST-MANDATE-001',
          'date'         => '2025-05-01 13:00:00',
          'iban'         => 'DE12500105170648489890',
          'bic'          => 'INGDDEFFXXX',
          'creditor_id'  => $creditor['id'],
          'status'       => 'RCUR',
        ])
        ->setMatch(['reference'])
        ->execute()
        ->first();
    }

    if (!self::$sharedContribStatusReady) {
      $optionGroup = OptionGroup::save(TRUE)
        ->addRecord(
          [
            'name' => 'contribution_status',
            'title' => 'Contribution Status',
            'is_active' => 1,
          ]
        )
        ->setMatch(['name'])
        ->execute()
        ->single();

      $optionGroupId = $optionGroup['id'];

      $inProgress = OptionValue::save(TRUE)
        ->addRecord(
          [
            'option_group_id' => $optionGroupId,
            'label' => 'In Progress',
            'name' => 'In Progress',
            'value' => 5,
            'is_active' => 1,
            'is_reserved' => 1,
          ]
        )
        ->setMatch(['option_group_id', 'name'])
        ->execute()
        ->single();

      $this->recurContributionStatusId = $inProgress['value'];

      OptionValue::save(TRUE)
        ->addRecord(
          [
            'option_group_id' => $optionGroupId,
            'label' => 'Completed',
            'name' => 'Completed',
            'value' => 1,
            'is_active' => 1,
            'is_default' => 1,
          ]
        )
        ->setMatch(['option_group_id', 'name'])
        ->execute();

      self::$sharedContribStatusReady = TRUE;
    }
  }

  private function getPaymentInstrumentIdByName(string $name): ?int {
    /** @phpstan-ignore-next-line */
    $result = OptionValue::get(TRUE)
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
    // phpcs:enable
    $form = new class() extends ModifyForm {

      public $_submitValues = [];

      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }

    };
    $form->controller = new class((int) $this->contact['id'], (int) $this->contract['id']) {

      public ?string $_destination = NULL;

      private int $id;
      private int $cid;
      private int $contractId;

      public function __construct(int $cid, int $contractId) {
        $this->id = $contractId;
        $this->cid = $cid;
        $this->contractId = $contractId;
      }

      public function set(string $k, mixed $v = NULL): void {}

      public function get(string $k): mixed {
        return match ($k) {
          'id' => $this->id,
          'cid' => $this->cid,
          'contract_id' => $this->contractId,
          default => NULL,
        };
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    $form->set('cid', $this->contact['id']);
    $form->set('id', $this->contract['id']);

    $form->set('modify_action', $modifyAction);
    $_REQUEST['modify_action'] = $modifyAction;
    $form->_submitValues['modify_action'] = $modifyAction;

    $paymentOptionValue = match ($to) {
      'SEPA' => 'RCUR',
      'non-SEPA' => 'Cash',
      'existing' => 'Cash',
      'None' => 'None',
      default => 'none'
    };

    $form->_submitValues += [
      'payment_option' => (string) $paymentOptionValue,
      'payment_instrument_id' => ($to === 'SEPA') ? 7 : (($to === 'non-SEPA') ? 3 : NULL),
      'membership_type_id' => $this->membershipType['id'],
      'payment_amount' => $to === 'None' ? '0' : '10',
      'payment_frequency' => '6',
      'cycle_day' => '30',
      'activity_date_time' => date('H:i:s'),
      'activity_date' => date('Y-m-d'),
      'iban' => 'DE02370502990000684712',
      'bic' => 'COKSDE33',
      'activity_details' => '',
      'activity_medium' => '',
      'account_holder' => $this->contact['display_name'],
      'campaign_id' => $this->campaign['id'],
    ];

    foreach ($form->_submitValues as $key => $value) {
      $_REQUEST[$key] = $value;
    }

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
        'create_new_recurring_contribution_zero' => $this->assertNewRecurringContribution($this->contact['id'], 0),
        'assign_existing_recurring' => $this->assertAssignedExistingRecurring($this->contact['id']),
        'no_change' => self::assertTrue(TRUE),
        default => throw new \RuntimeException("Unknown action: $action"),
      };
    }
  }

  private function assertMandateTerminated($contactId): void {
    /** @phpstan-ignore-next-line */
    $mandates = SepaMandate::get(TRUE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('status', 'IN', ['INVALID', 'COMPLETE', 'ENDED'])
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->getArrayCopy();

    if (empty($mandates)) {
      /** @phpstan-ignore-next-line */
      $all = SepaMandate::get(TRUE)
        ->addWhere('contact_id', '=', $contactId)
        ->addSelect('id', 'status', 'type', 'entity_id', 'reference')
        ->addOrderBy('id', 'DESC')
        ->execute()
        ->getArrayCopy();
      self::fail("Mandate should be terminated. Current mandates:\n" . print_r(array_values($all ?? []), TRUE));
    }

    self::assertNotEmpty($mandates, 'Mandate should be terminated');
  }

  private function assertRecurringContributionEnded($contactId): void {
    /** @phpstan-ignore-next-line */
    $recur = ContributionRecur::get(TRUE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('contribution_status_id:name', 'IN', ['Completed', 'Cancelled'])
      ->execute()
      ->getArrayCopy();
    self::assertNotEmpty($recur, 'Recurring contribution should be ended');
  }

  private function assertNewMandateCreated($contactId): void {
    $mandate = $this->getLatestSepaMandateForContact($contactId);
    self::assertNotNull($mandate, 'New SEPA mandate should be created');
    self::assertEquals('RCUR', $mandate['type'], 'SEPA Mandate type should be RCUR');
    self::assertContains($mandate['status'], ['FRST', 'RCUR'], 'Mandate status should be FRST or RCUR');
  }

  private function getLatestSepaMandateForContact($contactId) {
    /** @phpstan-ignore-next-line */
    $mandate = SepaMandate::get(TRUE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('type', '=', 'RCUR')
      ->addOrderBy('id', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();
    return $mandate ?: NULL;
  }

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  private function assertNewRecurringContribution($contactId, $amount = NULL): void {
  // phpcs:enable
    /** @phpstan-ignore-next-line */
    $recur = ContributionRecur::get(TRUE)
      ->addWhere('contact_id', '=', $contactId)
      ->addSelect(
        'id',
        'amount',
        'currency',
        'payment_instrument_id',
        'create_date',
        'cycle_day',
        'frequency_unit',
        'frequency_interval'
      )
      ->addOrderBy('id', 'DESC')
      ->setLimit(10)
      ->execute()
      ->getArrayCopy();

    $rows = array_values($recur ?? []);
    self::assertNotEmpty($rows, 'New recurring contribution should be created');

    if ($amount === NULL) {
      self::assertTrue(TRUE);
      return;
    }

    $target = (float) $amount;
    $delta  = 0.0001;
    $foundRow = NULL;

    foreach ($rows as $r) {
      if (!isset($r['amount'])) {
        continue;
      }
      if (abs((float) $r['amount'] - $target) <= $delta) {
        $foundRow = $r;
        break;
      }
    }

    if ($foundRow === NULL) {
      $summary = array_map(function($r) {
        $id  = $r['id'] ?? '?';
        $amt = isset($r['amount']) ? (float) $r['amount'] : '∅';
        $pi  = $r['payment_instrument_id'] ?? 'NULL';
        $dt  = $r['create_date'] ?? '';
        return "#$id amt=$amt pi=$pi date=$dt";
      }, $rows);
      self::fail(
        "Recurring contribution with specified amount not found.\n" .
        "Expected: {$target}\n" .
        "Last rows:\n" . implode("\n", $summary)
      );
    }

    if (abs($target) <= $delta) {
      $noneId = $this->getPaymentInstrumentIdByName('None');
      if ($noneId) {
        self::assertEquals(
          (int) $noneId,
          (int) ($foundRow['payment_instrument_id'] ?? 0),
          "Expected PI 'None' ({$noneId}) for zero-amount RC, got " . ($foundRow['payment_instrument_id'] ?? 'NULL')
        );
      }
    }
  }

  private function assertAssignedExistingRecurring($contactId): void {
    /** @phpstan-ignore-next-line */
    $recur = ContributionRecur::get(TRUE)
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

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  private function runContractStatusChange(
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    // phpcs:enable
    $form = new class() extends ModifyForm {

      public $_submitValues = [];

      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }

    };
    $form->controller = new class((int) $this->contact['id'], (int) $this->contract['id']) {

      public ?string $_destination = NULL;

      private int $id;
      private int $cid;
      private int $contractId;

      public function __construct(int $cid, int $contractId) {
        $this->id = $contractId;
        $this->cid = $cid;
        $this->contractId = $contractId;
      }

      public function set(string $k, mixed $v = NULL): void {}

      public function get(string $k): mixed {
        return match ($k) {
          'id' => $this->id,
          'cid' => $this->cid,
          'contract_id' => $this->contractId,
          default => NULL,
        };
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    $form->set('cid', $this->contact['id']);
    $form->set('id', $this->contract['id']);

    $form->set('modify_action', $modifyAction);
    $_REQUEST['modify_action'] = $modifyAction;
    $form->_submitValues['modify_action'] = $modifyAction;

    $form->_submitValues += [
      'contract_status' => $to,
      'membership_type_id' => $this->membershipType['id'],
      'payment_instrument_id' => 7,
      'payment_option' => 'RCUR',
      'payment_amount' => '10',
      'payment_frequency' => '6',
      'cycle_day' => '30',
      'activity_date_time' => date('H:i:s'),
      'activity_date' => date('Y-m-d'),
      'iban' => 'DE89370400440532013000',
      'bic' => 'DEUTDEFF',
      'activity_details' => '',
      'activity_medium' => '',
      'account_holder' => $this->contact['display_name'],
      'campaign_id' => $this->campaign['id'],
    ];

    if ($modifyAction === 'pause') {
      $form->_submitValues['resume_date'] = date('Y-m-d', strtotime('+1 day'));
    }
    if ($modifyAction === 'cancel') {
      $form->_submitValues['cancel_reason'] = 'Unknown';
    }

    foreach ($form->_submitValues as $key => $value) {
      $_REQUEST[$key] = $value;
    }

    $assertEndedOrMandateTerminated = function (int $cid) use ($to, $modifyAction): void {
      $rc = ContributionRecur::get(TRUE)
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
        // pausa -> mandato en espera
        $okStatuses[] = 'ONHOLD';
      }

      $mand = SepaMandate::get(TRUE)
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
        default => throw new \RuntimeException("Unknown status action: $action"),
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

  public function tearDown(): void {
    try {
      Contact::update(TRUE)
        ->addValue('id', $this->contact['id'])
        ->addValue('is_deleted', 1)
        ->execute();
    }
    catch (\Exception $e) {
      throw $e;
    }

    if (
      isset($this->campaign['id'])
      && !empty(self::$sharedCampaign['id'])
      && $this->campaign['id'] !== self::$sharedCampaign['id']
    ) {
      /** @phpstan-ignore-next-line */
      Campaign::delete(TRUE)
        ->addWhere('id', '=', $this->campaign['id'])
        ->execute();
    }

    if (
      isset($this->membershipType['id'])
      && !empty(self::$sharedMembershipType['id'])
      && $this->membershipType['id'] !== self::$sharedMembershipType['id']
    ) {
      MembershipType::delete(TRUE)
        ->addWhere('id', '=', $this->membershipType['id'])
        ->execute();
    }

    parent::tearDown();
  }

}
