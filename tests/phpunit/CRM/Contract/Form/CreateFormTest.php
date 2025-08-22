<?php

declare(strict_types = 1);

use Civi\Api4\Campaign;
use Civi\Api4\Contact;
use Civi\Api4\CustomGroup;
use Civi\Api4\MembershipType;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Create as CreateForm;

/**
 * @group headless
 */
class CreateFormTest extends ContractTestBase {

  protected static ?int $sharedOwnerOrgId = NULL;

  protected static array $sharedMembershipType = [];

  protected static array $sharedCampaign = [];


  protected static bool $sharedContribStatusReady = FALSE;

  protected static ?string $sharedCampaignTypeName = NULL;

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $contact;

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $campaign = [];

  /**
   * @phpstan-var array<string, mixed>
   */
  protected array $membershipType = [];

  protected ?int $recurContributionStatusId = NULL;

  public static function setUpBeforeClass(): void {
    /** @phpstan-ignore-next-line */
    $org = Contact::create(FALSE)
      ->addValue('contact_type', 'Organization')
      ->addValue('organization_name', 'CreateFormTest Owner Org ' . rand(1, 1000000))
      ->execute()
      ->single();
    self::$sharedOwnerOrgId = (int) $org['id'];

    CustomGroup::save(FALSE)
      ->addRecord([
        'title' => 'Membership General',
        'name' => 'membership_general',
        'extends' => 'Membership',
        'is_active' => 1,
        'style' => 'Inline',
      ])
      ->setMatch(['name'])
      ->execute()
      ->single();

    self::ensurePaymentInstrumentNone();

    $statusGroup = OptionGroup::save(TRUE)
      ->addRecord([
        'name' => 'contribution_status',
        'title' => 'Contribution Status',
        'is_active' => 1,
      ])
      ->setMatch(['name'])
      ->execute()
      ->single();

    self::ensureContributionStatuses((int) $statusGroup['id']);
    self::$sharedContribStatusReady = TRUE;

    $campaignType = OptionValue::save(FALSE)
      ->addRecord([
        'option_group_id:name' => 'campaign_type',
        'name' => 'test_campaign_type_shared',
        'label' => 'Test Campaign Type (shared)',
        'value' => '',
        'is_active' => 1,
      ])
      ->setMatch(['option_group_id', 'name'])
      ->execute()
      ->single();
    self::$sharedCampaignTypeName = $campaignType['name'];

    self::$sharedCampaign = Campaign::save(FALSE)
      ->addRecord([
        'title' => 'Test Campaign (shared)',
        'campaign_type_id:name' => self::$sharedCampaignTypeName,
        'status_id' => 1,
        'is_active' => 1,
      ])
      ->setMatch(['title', 'campaign_type_id'])
      ->execute()
      ->single();

    self::$sharedMembershipType = MembershipType::create(FALSE)
      ->setValues([
        'name' => 'Test Membership Type (shared)',
        'member_of_contact_id' => self::$sharedOwnerOrgId,
        'financial_type_id' => 2,
        'duration_unit' => 'year',
        'duration_interval' => 1,
        'period_type' => 'rolling',
        'is_active' => 1,
      ])
      ->execute()
      ->single();
  }

  public static function tearDownAfterClass(): void {
    try {
      if (!empty(self::$sharedCampaign['id'])) {
        /** @phpstan-ignore-next-line */
        civicrm_api3('Campaign', 'delete', ['id' => self::$sharedCampaign['id']]);
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
    $this->setupRecurContributionStatus();
    $this->createRequiredEntities();
  }

  private static function ensurePaymentInstrumentNone(): void {
    try {
      $none = OptionValue::get(TRUE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('option_group_id.is_active', '=', 1)
        ->addWhere('name', '=', 'None')
        ->setSelect(['id'])
        ->execute()
        ->single();

    }
    catch (\Throwable $e) {
      $none = NULL;
    }

    try {
      $legacy = OptionValue::get(TRUE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('option_group_id.is_active', '=', 1)
        ->addWhere('name', '=', 'no_payment_required')
        ->setSelect(['id'])
        ->execute()
        ->single();
    }
    catch (\Throwable $e) {
      $legacy = NULL;
    }

    if (!$none && $legacy) {
      OptionValue::update(TRUE)
        ->addWhere('id', '=', $legacy['id'])
        ->addValue('name', 'None')
        ->addValue('label', 'No Payment required')
        ->execute();
      \CRM_Core_PseudoConstant::flush();
      $none = ['id' => $legacy['id']];
    }
    elseif ($none && $legacy) {
      OptionValue::delete(TRUE)->addWhere('id', '=', $legacy['id'])->execute();
      \CRM_Core_PseudoConstant::flush();
    }

    if ($none) {
      return;
    }

    $row = OptionValue::get(TRUE)
      ->addWhere('option_group_id.name', '=', 'payment_instrument')
      ->addWhere('option_group_id.is_active', '=', 1)
      ->setSelect(['value'])
      ->addOrderBy('value', 'DESC')
      ->setLimit(1)
      ->execute()
      ->first();

    $next = isset($row['value']) && is_numeric($row['value']) ? ((int) $row['value']) + 1 : 1;

    OptionValue::create(TRUE)
      ->addValue('option_group_id.name', 'payment_instrument')
      ->addValue('label', 'No Payment required')
      ->addValue('name', 'None')
      ->addValue('value', $next)
      ->addValue('is_active', 1)
      ->addValue('is_reserved', 0)
      ->addValue('weight', 99)
      ->execute();

    \CRM_Core_PseudoConstant::flush();
  }

  private static function ensureContributionStatuses(int $groupId): void {
    OptionValue::save(TRUE)
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

    OptionValue::save(TRUE)
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

  private function setupRecurContributionStatus(): void {
    if (self::$sharedContribStatusReady) {
      $row = OptionValue::get(TRUE)
        ->addWhere('option_group_id:name', '=', 'contribution_status')
        ->addWhere('name', '=', 'In Progress')
        ->setSelect(['value'])
        ->setLimit(1)
        ->execute()
        ->first();
    }
    else {
      $statusGroup = OptionGroup::save(TRUE)
        ->addRecord([
          'name' => 'contribution_status',
          'title' => 'Contribution Status',
          'is_active' => 1,
        ])
        ->setMatch(['name'])
        ->execute();

      self::ensureContributionStatuses((int) $statusGroup['id']);

      $row = OptionValue::get(TRUE)
        ->addWhere('option_group_id:name', '=', 'contribution_status')
        ->addWhere('name', '=', 'In Progress')
        ->setSelect(['value'])
        ->setLimit(1)
        ->execute()
        ->first();

      self::$sharedContribStatusReady = TRUE;
    }

    $this->recurContributionStatusId = $row ? (int) $row['value'] : NULL;
  }

  private function createRequiredEntities(): void {
    $contact = $this->createContactWithRandomEmail();

    $this->contact = Contact::get(FALSE)
      ->addWhere('id', '=', $contact['id'])
      ->execute()
      ->single();

    $this->campaign = self::$sharedCampaign;
    $this->membershipType = self::$sharedMembershipType;
  }

  public function testFormValidationWithValidData_create_null(): void {
    $this->runFormValidationWithValidData('create', NULL);
  }

  private function runFormValidationWithValidData(string $paymentOption, mixed $membershipContract): void {
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

  public function testFormValidationWithValidData_create_1(): void {
    $this->runFormValidationWithValidData('create', 1);
  }

  public function testFormValidationWithValidData_RCUR_null(): void {
    $this->runFormValidationWithValidData('RCUR', NULL);
  }

  public function testFormValidationWithValidData_RCUR_1(): void {
    $this->runFormValidationWithValidData('RCUR', 1);
  }

  public function testFormValidationWithValidData_None_null(): void {
    $this->runFormValidationWithValidData('None', NULL);
  }

  public function testFormValidationWithValidData_None_1(): void {
    $this->runFormValidationWithValidData('None', 1);
  }

  public function testFormValidationWithValidData_empty_null(): void {
    $this->runFormValidationWithValidData('', NULL);
  }

  public function testFormValidationWithValidData_empty_1(): void {
    $this->runFormValidationWithValidData('', 1);
  }

  public function testFormSubmissionCreatesContract_None_0(): void {
    $this->runFormSubmissionCreatesContract('None', 0);
  }

  private function runFormSubmissionCreatesContract(string $paymentOption, mixed $paymentAmount): void {
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
      'payment_option' => $paymentOption,
      'join_date' => date('Y-m-d'),
      'end_date' => date('Y-m-d'),
      'activity_details' => '',
      'payment_amount' => $paymentAmount,
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
    self::assertEquals('TEST-001', $contract['membership_general.membership_contract']);
    self::assertEquals('REF-001', $contract['membership_general.membership_reference']);
    if ($paymentOption == 'None') {
      self::assertEquals('0.00', $contract['membership_payment.membership_annual']);
    }
    elseif ($paymentOption == 'RCUR') {
      $this->assertNotEmpty($contract['membership_payment.membership_recurring_contribution']);
    }
  }

  public function testFormSubmissionCreatesContract_RCUR_120(): void {
    $this->runFormSubmissionCreatesContract('RCUR', 120);
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
      civicrm_api3('Campaign', 'delete', ['id' => $this->campaign['id']]);
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
