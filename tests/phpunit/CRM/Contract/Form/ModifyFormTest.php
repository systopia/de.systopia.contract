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

  /**
   * @var array<string, mixed> */
  protected array $contact = [];

  /**
   * @var array<string, mixed> */
  protected array $membershipType = [];

  /**
   * @var array<string, mixed> */
  protected array $mandate = [];

  /**
   * @var array<string, mixed> */
  protected array $contract = [];

  protected ?int $recurContributionStatusId = NULL;

  protected ?string $initialPaymentMethod = NULL;
  protected array $campaign = [];

  public function setUp(): void {
    parent::setUp();
    $this->createRequiredEntities();
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

    $this->membershipType = MembershipType::create(TRUE)
      ->addValue('name', 'Modify Membership Type')
      ->addValue('member_of_contact_id', $this->contact['id'])
      ->addValue('financial_type_id', 2)
      ->addValue('duration_unit', 'year')
      ->addValue('duration_interval', 1)
      ->addValue('period_type', 'rolling')
      ->addValue('is_active', 1)
      ->execute()
      ->single();

    $this->campaign = \Civi\Api4\Campaign::create(FALSE)
      ->addValue('title', 'Test Campaign')
      ->addValue('name', 'test_campaign_' . rand(1, 1000000))
      ->addValue('status_id', 1)
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();

    $paymentOptionGroup = OptionGroup::save(TRUE)
      ->addRecord([
        'name' => 'payment_instrument',
        'title' => 'Payment Instrument',
        'is_active' => 1,
      ])
      ->setMatch(['name'])
      ->execute()
      ->single();

    $paymentOptionGroupId = $paymentOptionGroup['id'];

    $nonePaymentInstrument = OptionValue::save(TRUE)
      ->addRecord([
        'option_group_id' => $paymentOptionGroupId,
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

    $rcurId = $this->getPaymentInstrumentIdByName('RCUR');
    $cashId = $this->getPaymentInstrumentIdByName('Cash');
    $noneId = $this->getPaymentInstrumentIdByName('None');

    $isExistingSepa = $this->initialPaymentMethod === 'existing-SEPA';
    $isExistingNonSepa = $this->initialPaymentMethod === 'existing-non-SEPA';

    if ($isExistingSepa || $isExistingNonSepa) {
      $paymentInstrumentId = $isExistingSepa ? $rcurId : $cashId;
      $recurResult = \Civi\Api4\ContributionRecur::create(TRUE)
        ->addValue('contact_id', $contact['id'])
        ->addValue('amount', 10.00)
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
          ->addValue('iban', 'DE44500105175407324931')
          ->addValue('bic', 'DEUTDEFF500')
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
      return;
    }
    $creditors = civicrm_api3('SepaCreditor', 'get', []);
    foreach ($creditors['values'] as $creditor) {
      $iban = $creditor['iban'] ?? '';
      $bic  = $creditor['bic'] ?? '';
      $needsUpdate = empty($iban) || empty($bic);
      if ($needsUpdate) {
        civicrm_api3('SepaCreditor', 'create', [
          'id'  => $creditor['id'],
          'iban' => $iban ?: 'DE44500105175407324931',
          'bic'  => $bic  ?: 'DEUTDEFF500',
        ]);
      }
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
  }

  private function getPaymentInstrumentIdByName(string $name): ?int {
    try {
      $result = OptionValue::get(TRUE)
        ->addSelect('value')
        ->addWhere('option_group_id:name', '=', 'payment_instrument')
        ->addWhere('name', '=', $name)
        ->addWhere('is_active', '=', TRUE)
        ->execute()
        ->single();
      return (int) $result['value'];
    }
    catch (CRM_Core_Exception $exception) {
      return NULL;
    }
  }

  public function paymentInstrumentChangeProvider(): array {
    return [
      ['SEPA', 'SEPA', ['terminate_mandate', 'create_new_mandate'], 'update'],
      ['SEPA', 'non-SEPA', ['terminate_mandate', 'create_new_recurring_contribution'], 'update'],
      ['SEPA', 'existing', ['terminate_mandate', 'assign_existing_recurring'], 'update'],
      ['SEPA', 'None', ['terminate_mandate', 'create_new_recurring_contribution_zero'], 'update'],
      ['non-SEPA', 'SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update'],
      ['non-SEPA', 'non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update'],
      ['non-SEPA', 'existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update'],
      ['non-SEPA', 'None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update'],
      ['existing-SEPA', 'SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update'],
      ['existing-SEPA', 'non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update'],
      ['existing-SEPA', 'existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update'],
      ['existing-SEPA', 'None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update'],
      ['existing-non-SEPA', 'SEPA', ['end_recurring_contribution', 'create_new_mandate'], 'update'],
      ['existing-non-SEPA', 'non-SEPA', ['end_recurring_contribution', 'create_new_recurring_contribution'], 'update'],
      ['existing-non-SEPA', 'existing', ['end_recurring_contribution', 'assign_existing_recurring'], 'update'],
      ['existing-non-SEPA', 'None', ['end_recurring_contribution', 'create_new_recurring_contribution_zero'], 'update'],
      ['None', 'SEPA', ['end_recurring_contribution_zero', 'create_new_mandate'], 'update'],
      ['None', 'non-SEPA', ['end_recurring_contribution_zero', 'create_new_recurring_contribution'], 'update'],
      ['None', 'existing', ['end_recurring_contribution_zero', 'assign_existing_recurring'], 'update'],
      ['None', 'None', ['no_change'], 'update'],
    ];
  }

  public function contractStatusChangeProvider(): array {
    return [
      ['new', 'active', ['create_recurring_contribution'], 'update'],
      ['new', 'paused', ['maybe_create_paused'], 'pause'],
      ['new', 'ended', ['maybe_create_ended'], 'cancel'],
      ['active', 'active', ['no_change'], 'update'],
      ['active', 'paused', ['terminate_mandate_or_end_recurring'], 'pause'],
      ['active', 'ended', ['terminate_mandate_or_end_recurring'], 'cancel'],
      ['paused', 'active', ['create_mandate_or_recurring'], 'update'],
      ['paused', 'paused', ['no_change'], 'pause'],
      ['paused', 'ended', ['no_actions_needed'], 'cancel'],
    ];
  }

  private function getLatestSepaMandateForContact($contactId) {
    $mandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id' => $contactId,
      'type' => 'RCUR',
      'options' => ['sort' => 'id DESC', 'limit' => 1],
    ]);
    return !empty($mandates['values']) ? reset($mandates['values']) : NULL;
  }

  private function assertNewMandateCreated($contactId): void {
    $mandate = $this->getLatestSepaMandateForContact($contactId);
    self::assertNotNull($mandate, 'New SEPA mandate should be created');
    self::assertEquals('RCUR', $mandate['type'], 'SEPA Mandate type should be RCUR');
    self::assertContains($mandate['status'], ['FRST', 'RCUR'], 'Mandate status should be FRST or RCUR');
  }

  private function assertMandateTerminated($contactId): void {
    $mandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id' => $contactId,
      'status' => ['IN' => ['INVALID', 'COMPLETE', 'ENDED']],
    ]);
    self::assertNotEmpty($mandates['values'], 'Mandate should be terminated');
  }

  private function assertRecurringContributionEnded($contactId): void {
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
      'contribution_status_id' => ['IN' => [1, 'Completed', 2, 'Cancelled']],
    ]);
    self::assertNotEmpty($recur['values'], 'Recurring contribution should be ended');
  }

  private function assertNewRecurringContribution($contactId, $amount = NULL): void {
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
    ]);
    self::assertNotEmpty($recur['values'], 'New recurring contribution should be created');
    if ($amount !== NULL) {
      $found = FALSE;
      foreach ($recur['values'] as $row) {
        if (isset($row['amount']) && (float) $row['amount'] === (float) $amount) {
          $found = TRUE;
        }
      }
      self::assertTrue($found, 'Recurring contribution with specified amount found');
    }
  }

  private function assertAssignedExistingRecurring($contactId): void {
    $recur = civicrm_api3('ContributionRecur', 'get', [
      'contact_id' => $contactId,
      'is_test' => 0,
    ]);
    self::assertNotEmpty($recur['values'], 'Should have assigned existing recurring contribution');
  }

  /**
   * @dataProvider paymentInstrumentChangeProvider
   */
  public function testPaymentInstrumentChange(
    string $from,
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $this->initialPaymentMethod = $from;
    $this->createRequiredEntities();

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
        return match($k) {
          'id' => $this->id, 'cid' => $this->cid, 'contract_id' => $this->contractId, default => NULL,
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

  /**
   * @dataProvider contractStatusChangeProvider
   */
  public function testContractStatusChange(
    string $from,
    string $to,
    array $expectedActions,
    string $modifyAction
  ): void {
    $this->initialPaymentMethod = 'SEPA';
    $this->createRequiredEntities();

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
        return match($k) {
          'id' => $this->id, 'cid' => $this->cid, 'contract_id' => $this->contractId, default => NULL,
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

    $form->preProcess();
    $form->buildQuickForm();
    $form->setDefaults($form->_submitValues);
    $form->postProcess();

    foreach ($expectedActions as $action) {
      match ($action) {
        'create_recurring_contribution' => $this->assertNewRecurringContribution($this->contact['id']),
        'maybe_create_paused' => self::assertTrue(TRUE),
        'maybe_create_ended' => self::assertTrue(TRUE),
        'terminate_mandate_or_end_recurring' => $this->assertRecurringContributionEnded($this->contact['id']),
        'create_mandate_or_recurring' => $this->assertNewMandateCreated($this->contact['id']),
        'no_change' => self::assertTrue(TRUE),
        'no_actions_needed' => self::assertTrue(TRUE),
        default => throw new \RuntimeException("Unknown status action: $action"),
      };
    }
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

    if (isset($this->campaign['id']) && $this->campaign['id'] !== 0) {
      /** @phpstan-ignore-next-line */
      civicrm_api3('Campaign', 'delete', ['id' => $this->campaign['id']]);
    }

    if (isset($this->membershipType['id']) && $this->membershipType['id'] !== 0) {
      MembershipType::delete(TRUE)
        ->addWhere('id', '=', $this->membershipType['id'])
        ->execute();
    }

    parent::tearDown();
  }

}
