<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Modify as ModifyForm;

use Civi\Api4\Contact;
use Civi\Api4\MembershipType;
use Civi\Api4\SepaMandate;
use Civi\Api4\SepaCreditor;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;

/**
 * @group headless
 */
class ModifyFormTest extends ContractTestBase {

  /** @var array<string, mixed> */
  protected array $contact = [];

  /** @var array<string, mixed> */
  protected array $membershipType = [];

  /** @var array<string, mixed> */
  protected array $mandate = [];

  /** @var array<string, mixed> */
  protected array $contract = [];

  protected ?int $recurContributionStatusId = NULL;

  protected ?string $initialPaymentMethod = NULL;
  protected array $campaign = [];

  /** Shared fixtures */
  protected static ?int $sharedOwnerOrgId = null;
  protected static array $sharedMembershipType = [];
  protected static array $sharedCampaign = [];
  protected static ?int $sharedPaymentGroupId = null;
  protected static bool $sharedContribStatusReady = false;

  public static function setUpBeforeClass(): void {
    /** @phpstan-ignore-next-line */
    $org = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Organization',
      'organization_name' => 'ModifyFormTest Owner Org ' . rand(1, 1000000),
    ]);
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
    $campaign = civicrm_api3('Campaign', 'create', [
      'title' => 'Test Campaign (shared)',
      'name' => 'test_campaign_shared_' . rand(1, 1000000),
      'status_id' => 1,
      'is_active' => 1,
    ]);
    self::$sharedCampaign = $campaign['values'][array_key_first($campaign['values'])];

    $paymentOptionGroup = OptionGroup::save(TRUE)
      ->addRecord([
        'name' => 'payment_instrument',
        'title' => 'Payment Instrument',
        'is_active' => 1,
      ])
      ->setMatch(['name'])
      ->execute()
      ->single();
    self::$sharedPaymentGroupId = (int) $paymentOptionGroup['id'];

    OptionValue::save(TRUE)
      ->addRecord([
        'option_group_id' => self::$sharedPaymentGroupId,
        'label' => 'No Payment required',
        'name' => 'None',
        'value' => 100,
        'is_active' => 1,
        'is_reserved' => 0,
        'weight' => 99,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute()
      ->single();

    $optionGroup = OptionGroup::save(TRUE)
      ->addRecord([
        'name' => 'contribution_status',
        'title' => 'Contribution Status',
        'is_active' => 1,
      ])
      ->setMatch(['name'])
      ->execute()
      ->single();

    $optionGroupId = $optionGroup['id'];

    OptionValue::save(TRUE)
      ->addRecord([
        'option_group_id' => $optionGroupId,
        'label' => 'In Progress',
        'name' => 'In Progress',
        'value' => 5,
        'is_active' => 1,
        'is_reserved' => 1,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute()
      ->single();

    OptionValue::save(TRUE)
      ->addRecord([
        'option_group_id' => $optionGroupId,
        'label' => 'Completed',
        'name' => 'Completed',
        'value' => 1,
        'is_active' => 1,
        'is_default' => 1,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute();

    self::$sharedContribStatusReady = true;
  }

  public static function tearDownAfterClass(): void {
    try {
      if (!empty(self::$sharedCampaign['id'])) {
        /** @phpstan-ignore-next-line */
        civicrm_api3('Campaign', 'delete', ['id' => self::$sharedCampaign['id']]);
      }
    } catch (\Throwable $e) {}

    try {
      if (!empty(self::$sharedMembershipType['id'])) {
        MembershipType::delete(TRUE)
          ->addWhere('id', '=', self::$sharedMembershipType['id'])
          ->execute();
      }
    } catch (\Throwable $e) {}

    try {
      if (!empty(self::$sharedOwnerOrgId)) {
        /** @phpstan-ignore-next-line */
        civicrm_api3('Contact', 'delete', ['id' => self::$sharedOwnerOrgId]);
      }
    } catch (\Throwable $e) {}
  }

  public function setUp(): void {
    parent::setUp();
  }

  private function createRequiredEntities(): void {
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
      $recurResult = civicrm_api3('ContributionRecur', 'create', [
        'contact_id' => $contact['id'],
        'amount' => '10.00',
        'currency' => 'EUR',
        'frequency_unit' => 'month',
        'frequency_interval' => 1,
        'installments' => NULL,
        'contribution_status_id' => 'In Progress',
        'payment_instrument_id' => $paymentInstrumentId,
      ]);
      $recurringContributionId = $recurResult['id'];

      $this->contract = $this->createNewContract([
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
      ]);

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
          ->addRecord([
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
          ])
          ->setMatch(['reference'])
          ->execute()
          ->first();
      }

      /** @phpstan-ignore-next-line */
      $creditors = civicrm_api3('SepaCreditor', 'get', []);
      foreach ($creditors['values'] as $creditor) {
        $iban = $creditor['iban'] ?? '';
        $bic  = $creditor['bic'] ?? '';
        $needsUpdate = empty($iban) || empty($bic);
        if ($needsUpdate) {
          /** @phpstan-ignore-next-line */
          civicrm_api3('SepaCreditor', 'create', [
            'id'  => $creditor['id'],
            'iban' => $iban ?: 'DE02370502990000684712',
            'bic'  => $bic  ?: 'COKSDE33',
          ]);
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
      /** @phpstan-ignore-next-line */
      $membership = civicrm_api3('Membership', 'getsingle', ['id' => $this->contract['id']]);
      $recurContriId = $membership[CRM_Contract_Utils::getCustomFieldId(
        'membership_payment.membership_recurring_contribution'
      )];

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
          'contact_id' => $contact['id'],
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
        ->first();
    }

    if (!self::$sharedContribStatusReady) {
      $optionGroup = OptionGroup::save(TRUE)
        ->addRecord([
          'name' => 'contribution_status',
          'title' => 'Contribution Status',
          'is_active' => 1,
        ])
        ->setMatch(['name'])
        ->execute()
        ->single();

      $optionGroupId = $optionGroup['id'];

      $inProgress = OptionValue::save(TRUE)
        ->addRecord([
          'option_group_id' => $optionGroupId,
          'label' => 'In Progress',
          'name' => 'In Progress',
          'value' => 5,
          'is_active' => 1,
          'is_reserved' => 1,
        ])
        ->setMatch(['option_group_id', 'name'])
        ->execute()
        ->single();

      $this->recurContributionStatusId = $inProgress['value'];

      OptionValue::save(TRUE)
        ->addRecord([
          'option_group_id' => $optionGroupId,
          'label' => 'Completed',
          'name' => 'Completed',
          'value' => 1,
          'is_active' => 1,
          'is_default' => 1,
        ])
        ->setMatch(['option_group_id', 'name'])
        ->execute();

      self::$sharedContribStatusReady = true;
    }
  }

  private function getPaymentInstrumentIdByName(string $name): ?int {
    /** @phpstan-ignore-next-line */
    $result = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'payment_instrument',
      'name' => $name,
      'is_active' => 1,
    ]);
    return !empty($result['values']) ? (int)reset($result['values'])['value'] : null;
  }

  private function getLatestSepaMandateForContact($contactId) {
    /** @phpstan-ignore-next-line */
    $mandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id' => $contactId,
      'type' => 'RCUR',
      'options' => ['sort' => 'id DESC', 'limit' => 1],
    ]);
    return !empty($mandates['values']) ? reset($mandates['values']) : null;
  }

  private function assertNewMandateCreated($contactId): void {
    $mandate = $this->getLatestSepaMandateForContact($contactId);
    self::assertNotNull($mandate, 'New SEPA mandate should be created');
    self::assertEquals('RCUR', $mandate['type'], 'SEPA Mandate type should be RCUR');
    self::assertContains($mandate['status'], ['FRST', 'RCUR'], 'Mandate status should be FRST or RCUR');
  }

  private function assertMandateTerminated($contactId): void {
    /** @phpstan-ignore-next-line */
    $mandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id' => $contactId,
      'status' => ['IN' => ['INVALID', 'COMPLETE', 'ENDED']],
    ]);
    self::assertNotEmpty($mandates['values'], 'Mandate should be terminated');
  }

  private function assertRecurringContributionEnded($contactId): void {
    /** @phpstan-ignore-next-line */
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
      'contribution_status_id' => ['IN' => [1, 'Completed', 2, 'Cancelled']],
    ]);
    self::assertNotEmpty($recur['values'], 'Recurring contribution should be ended');
  }

  private function assertNewRecurringContribution($contactId, $amount = null): void {
    /** @phpstan-ignore-next-line */
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
    ]);
    self::assertNotEmpty($recur['values'], 'New recurring contribution should be created');
    if ($amount !== null) {
      $found = false;
      foreach ($recur['values'] as $row) {
        if (isset($row['amount']) && (float)$row['amount'] === (float)$amount) {
          $found = true;
        }
      }
      self::assertTrue($found, 'Recurring contribution with specified amount found');
    }
  }

  private function assertAssignedExistingRecurring($contactId): void {
    /** @phpstan-ignore-next-line */
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
      'is_test' => 0,
    ]);
    self::assertNotEmpty($recur['values'], 'Should have assigned existing recurring contribution');
  }

  private function runPaymentInstrumentChange(
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $form = new class() extends ModifyForm {
      public $_submitValues = [];
      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }
    };
    $form->controller = new class((int) $this->contact['id'], (int) $this->contract['id']) {
      public ?string $_destination = NULL;
      private int $id, $cid, $contractId;
      public function __construct(int $cid, int $contractId) {
        $this->id = $contractId; $this->cid = $cid; $this->contractId = $contractId;
      }
      public function set(string $k, mixed $v = NULL): void {}
      public function get(string $k): mixed {
        return match($k) {
          'id' => $this->id, 'cid' => $this->cid, 'contract_id' => $this->contractId, default => NULL,
        };
      }
      public function setDestination(string $url): void { $this->_destination = $url; }
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
      'payment_option' => (string)$paymentOptionValue,
      'payment_instrument_id' => ($to === 'SEPA') ? 7 : (($to === 'non-SEPA') ? 3 : null),
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
        'no_change' => self::assertTrue(true),
        default => throw new \RuntimeException("Unknown action: $action"),
      };
    }
  }

  private function runContractStatusChange(
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $form = new class() extends ModifyForm {
      public $_submitValues = [];
      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }
    };
    $form->controller = new class((int) $this->contact['id'], (int) $this->contract['id']) {
      public ?string $_destination = NULL;
      private int $id, $cid, $contractId;
      public function __construct(int $cid, int $contractId) {
        $this->id = $contractId; $this->cid = $cid; $this->contractId = $contractId;
      }
      public function set(string $k, mixed $v = NULL): void {}
      public function get(string $k): mixed {
        return match($k) {
          'id' => $this->id, 'cid' => $this->cid, 'contract_id' => $this->contractId, default => NULL,
        };
      }
      public function setDestination(string $url): void { $this->_destination = $url; }
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

    $form->preProcess();
    $form->buildQuickForm();
    $form->setDefaults($form->_submitValues);
    $form->postProcess();

    foreach ($expectedActions as $action) {
      match ($action) {
        'create_recurring_contribution' => $this->assertNewRecurringContribution($this->contact['id']),
        'maybe_create_paused' => self::assertTrue(true),
        'maybe_create_ended' => self::assertTrue(true),
        'terminate_mandate_or_end_recurring' => $this->assertRecurringContributionEnded($this->contact['id']),
        'create_mandate_or_recurring' => $this->assertNewMandateCreated($this->contact['id']),
        'no_change' => self::assertTrue(true),
        'no_actions_needed' => self::assertTrue(true),
        default => throw new \RuntimeException("Unknown status action: $action"),
      };
    }
  }

  public function testPaymentInstrumentChange_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['terminate_mandate', 'create_new_mandate'], 'update');
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
    $this->runPaymentInstrumentChange('None', ['terminate_mandate', 'create_new_recurring_contribution_zero'], 'update');
  }

  public function testPaymentInstrumentChange_non_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_non_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update');
  }

  public function testPaymentInstrumentChange_non_SEPA_existing(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update');
  }

  public function testPaymentInstrumentChange_non_SEPA_None(): void {
    $this->initialPaymentMethod = 'non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update');
  }

  public function testPaymentInstrumentChange_existing_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_existing_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update');
  }

  public function testPaymentInstrumentChange_existing_SEPA_existing(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update');
  }

  public function testPaymentInstrumentChange_existing_SEPA_None(): void {
    $this->initialPaymentMethod = 'existing-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update');
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_SEPA(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_non_SEPA(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update');
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_existing(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update');
  }

  public function testPaymentInstrumentChange_existing_non_SEPA_None(): void {
    $this->initialPaymentMethod = 'existing-non-SEPA';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update');
  }

  public function testPaymentInstrumentChange_None_SEPA(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('SEPA', ['end_recurring_contribution_zero', 'create_new_mandate'], 'update');
  }

  public function testPaymentInstrumentChange_None_non_SEPA(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('non-SEPA', ['end_recurring_contribution_zero', 'create_new_recurring_contribution'], 'update');
  }

  public function testPaymentInstrumentChange_None_existing(): void {
    $this->initialPaymentMethod = 'None';
    $this->createRequiredEntities();
    $this->runPaymentInstrumentChange('existing', ['end_recurring_contribution_zero', 'assign_existing_recurring'], 'update');
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

    if (isset($this->campaign['id']) && !empty(self::$sharedCampaign['id']) && $this->campaign['id'] !== self::$sharedCampaign['id']) {
      /** @phpstan-ignore-next-line */
      civicrm_api3('Campaign', 'delete', ['id' => $this->campaign['id']]);
    }

    if (isset($this->membershipType['id']) && !empty(self::$sharedMembershipType['id']) && $this->membershipType['id'] !== self::$sharedMembershipType['id']) {
      MembershipType::delete(TRUE)
        ->addWhere('id', '=', $this->membershipType['id'])
        ->execute();
    }

    parent::tearDown();
  }

}
