<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Create as CreateForm;

use Civi\Api4\Contact;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\CustomGroup;
use Civi\Api4\CustomField;
use Civi\Api4\MembershipType;

/**
 * @group headless
 */
class CreateFormTest extends ContractTestBase {

  /**
   * @var array<string, mixed> */
  protected array $contact;

  /**
   * @var array<string, mixed> */
  protected array $campaign = [];

  /**
   * @var array<string, mixed> */
  protected array $membershipType = [];
  protected ?string $recurContributionStatusId = NULL;

  public function setUp(): void {

    Contact::get(TRUE);
    parent::setUp();
    $this->setupRecurContributionStatus();
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

    $this->contact = $contactResult[0];

    try {
      $group = OptionGroup::get(TRUE)
        ->addWhere('name', '=', 'campaign_type')
        ->execute();
      $optionGroupId = $group[0]['id'];

      $existing = OptionValue::get(TRUE)
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', 'test_campaign_type')
        ->execute();

      if ($existing->count() === 0) {
        OptionValue::create(TRUE)
          ->addValue('option_group_id', $optionGroupId)
          ->addValue('label', 'Test Campaign Type')
          ->addValue('name', 'test_campaign_type')
          ->addValue('is_active', 1)
          ->execute();
      }

      /** @phpstan-ignore-next-line */
      $existingCampaign = civicrm_api3('Campaign', 'get', [
        'title' => 'Test Campaign',
        'campaign_type_id' => 1,
      ]);

      if (isset($existingCampaign['values']) && $existingCampaign['values'] !== []) {
        $this->campaign = reset($existingCampaign['values']);
      }
      else {
        /** @phpstan-ignore-next-line */
        $campaign = civicrm_api3('Campaign', 'create', [
          'title' => 'Test Campaign',
          'campaign_type_id' => 1,
          'status_id' => 1,
          'is_active' => 1,
        ]);
        $this->campaign = $campaign['values'][$campaign['id']];
      }

      $membershipGeneralGroup = CustomGroup::get(TRUE)
        ->addWhere('name', '=', 'membership_general')
        ->execute();

      if ($membershipGeneralGroup->count() === 0) {
        $membershipGeneralGroup = CustomGroup::create(TRUE)
          ->addValue('title', 'Membership General')
          ->addValue('name', 'membership_general')
          ->addValue('extends', 'Membership')
          ->addValue('is_active', 1)
          ->addValue('style', 'Inline')
          ->execute();
      }
      $groupId = $membershipGeneralGroup[0]['id'];

      $membershipNotesField = CustomField::get(TRUE)
        ->addWhere('custom_group_id', '=', $groupId)
        ->addWhere('name', '=', 'membership_notes')
        ->execute();

      if ($membershipNotesField->count() === 0) {
        CustomField::create(TRUE)
          ->addValue('custom_group_id', $groupId)
          ->addValue('label', 'Membership Notes')
          ->addValue('name', 'membership_notes')
          ->addValue('data_type', 'Memo')
          ->addValue('html_type', 'TextArea')
          ->addValue('is_active', 1)
          ->addValue('is_searchable', 1)
          ->execute();
      }

    }
    catch (Exception $e) {
      throw $e;
    }

    $membershipType = MembershipType::create(TRUE)
      ->addValue('name', 'Test Membership Type')
      ->addValue('member_of_contact_id', $this->contact['id'])
      ->addValue('financial_type_id', 2)
      ->addValue('duration_unit', 'year')
      ->addValue('duration_interval', 1)
      ->addValue('period_type', 'rolling')
      ->addValue('is_active', 1)
      ->execute();

    $this->membershipType = $membershipType[0];
  }

  private function setupRecurContributionStatus(): void {
    try {
      $optionGroupResult = OptionGroup::get(TRUE)
        ->addWhere('name', '=', 'contribution_status')
        ->execute();

      if ($optionGroupResult->count() === 0) {
        $optionGroupResult = OptionGroup::create(TRUE)
          ->addValue('name', 'contribution_status')
          ->addValue('title', 'Contribution Status')
          ->addValue('is_active', 1)
          ->execute();
      }

      $optionGroupId = $optionGroupResult[0]['id'];

      $status = OptionValue::get(TRUE)
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', 'In Progress')
        ->addWhere('is_active', '=', 1)
        ->execute();

      if ($status->count() === 0) {
        OptionValue::create(TRUE)
          ->addValue('option_group_id', $optionGroupId)
          ->addValue('label', 'In Progress')
          ->addValue('name', 'In Progress')
          ->addValue('value', 5)
          ->addValue('is_active', 1)
          ->addValue('is_reserved', 1)
          ->execute();
        $this->recurContributionStatusId = '5';
      }
      else {
        $this->recurContributionStatusId = $status[0]['value'];
      }

      $completedStatus = OptionValue::get(TRUE)
        ->addWhere('option_group_id', '=', $optionGroupId)
        ->addWhere('name', '=', 'Completed')
        ->execute();

      if ($completedStatus->count() === 0) {
        OptionValue::create(TRUE)
          ->addValue('option_group_id', $optionGroupId)
          ->addValue('label', 'Completed')
          ->addValue('name', 'Completed')
          ->addValue('value', 1)
          ->addValue('is_active', 1)
          ->addValue('is_default', 1)
          ->execute();
      }

    }
    catch (\Exception $e) {
      /** @phpstan-ignore-next-line */
      throw new \CRM_Core_Exception($e->getMessage(), 0, $e);
    }
  }

  /**
   * @dataProvider combinedFormDataProvider
   */
  public function testFormValidationWithValidData(string $paymentOption, mixed $membershipContract): void {
    $cid = $this->contact['id'];
    $today = date('Y-m-d');

    $validValues = [
      'payment_option' => $paymentOption,
      'payment_amount' => '120',
      'payment_frequency' => '12',
      'iban' => 'DE89370400440532013000',
      'bic' => 'DEUTDEFF',
      'start_date' => $today,
      'join_date' => $today,
      'end_date' => $today,
      'membership_type_id' => $this->membershipType['id'],
      'account_holder' => $this->contact['display_name'],
      '_qf_CreateForm_next' => '1',
    ];

    if ($membershipContract !== NULL) {
      $validValues['membership_contract'] = $membershipContract;
    }

    /** @var \CRM_Contract_Form_Create&PHPUnit\Framework\MockObject\MockObject $form */
    $form = $this->getMockBuilder(CreateForm::class)
      ->onlyMethods(['exportValues'])
      ->disableOriginalConstructor()
      ->getMock();

    // @phpstan-ignore-next-line
    $form->method('exportValues')->willReturn($validValues);

    /** @phpstan-ignore-next-line */
    $form->controller = new class($cid) {
      public ?string $_destination = NULL;
      private int $cid;

      public function __construct(int $cid) {
        $this->cid = $cid;
      }

      public function set(string $k, mixed $v = NULL): void {}

      public function get(string $k): mixed {
        return $k === 'cid' ? $this->cid : NULL;
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    $refl = new ReflectionClass($form);
    $cidProp = $refl->getProperty('cid');
    $cidProp->setAccessible(TRUE);
    $cidProp->setValue($form, $cid);

    /** @phpstan-ignore-next-line */
    $form->preProcess();
    /** @phpstan-ignore-next-line */
    $form->_submitValues = $validValues;
    $form->setDefaults($validValues);

    self::assertTrue($form->validate());
  }

  /**
   * @return array<string, array{0: string, 1: mixed}>
   */
  public function combinedFormDataProvider(): array {
    return [
      'create + null' => ['create', NULL],
      'create + 1' => ['create', 1],
      'RCUR + null' => ['RCUR', NULL],
      'RCUR + 1' => ['RCUR', 1],
      'empty + null' => ['', NULL],
      'empty + 1' => ['', 1],
    ];
  }

  public function testFormSubmissionCreatesContract(): void {
    $cid = $this->contact['id'];
    $form = new CreateForm();

    /** @phpstan-ignore-next-line */
    $form->controller = new class($cid) {
      public ?string $_destination = NULL;
      private int $cid;

      public function __construct(int $cid) {
        $this->cid = $cid;
      }

      public function set(string $k, mixed $v = NULL): void {}

      public function get(string $k): mixed {
        return $k === 'cid' ? $this->cid : NULL;
      }

      public function setDestination(string $url): void {
        $this->_destination = $url;
      }

    };

    /** @phpstan-ignore-next-line */
    $form->set('cid', $this->contact['id']);
    /** @phpstan-ignore-next-line */
    $form->preProcess();
    $form->buildQuickForm();

    $submissionValues = [
      'payment_option' => 'RCUR',
      'join_date'      => date('Y-m-d'),
      'end_date'       => date('Y-m-d'),
      'membership_dialoger' => '',
      'activity_details' => '',
      'payment_amount' => '120',
      'payment_frequency' => '12',
      'iban' => 'DE89370400440532013000',
      'bic' => 'DEUTDEFF',
      'start_date' => date('Y-m-d'),
      'membership_type_id' => $this->membershipType['id'],
      'campaign_id' => $this->campaign['id'] ?? NULL,
      'account_holder' => $this->contact['display_name'],
      'membership_contract' => 'TEST-001',
      'membership_reference' => 'REF-001',
    ];

    /** @phpstan-ignore-next-line */
    $form->_submitValues = $submissionValues;
    $form->setDefaults($submissionValues);
    $form->postProcess();

    /** @phpstan-ignore-next-line */
    $result = civicrm_api3('Contract', 'getsingle', [
      'contact_id' => $this->contact['id'],
    ]);

    $contract = $this->getContract($result['id']);

    self::assertEquals($this->contact['id'], $contract['contact_id']);
    self::assertEquals($this->membershipType['id'], $contract['membership_type_id']);
    self::assertEquals(12, $contract['membership_payment.membership_frequency']);
    self::assertEquals('1,440.00', $contract['membership_payment.membership_annual']);
    self::assertEquals('TEST-001', $contract['membership_general.membership_contract']);
    self::assertEquals('REF-001', $contract['membership_general.membership_reference']);

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
