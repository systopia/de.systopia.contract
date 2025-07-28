<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Create as CreateForm;

use Civi\Api4\Contact;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;
use Civi\Api4\Campaign;
use Civi\Api4\CustomGroup;
use Civi\Api4\CustomField;
use Civi\Api4\MembershipType;
use Civi\Api4\ContributionRecur;

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
  protected ?int $recurContributionStatusId = NULL;

  protected static ?int $sharedOwnerOrgId = null;
  protected static array $sharedMembershipType = [];
  protected static array $sharedCampaign = [];
  protected static ?int $sharedPaymentGroupId = null;
  protected static bool $sharedContribStatusReady = false;
  protected static ?string $sharedCampaignTypeName = null;

  public static function setUpBeforeClass(): void {
    /** @phpstan-ignore-next-line */
    $org = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Organization',
      'organization_name' => 'CreateFormTest Owner Org ' . rand(1, 1000000),
    ]);
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
    $this->setupRecurContributionStatus();
    $this->createRequiredEntities();
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

  private function setupRecurContributionStatus(): void {
    if (self::$sharedContribStatusReady) {
      return;
    }
    try {
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
    catch (\Exception $e) {
      /** @phpstan-ignore-next-line */
      throw new \CRM_Core_Exception($e->getMessage(), 0, $e);
    }
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

      public function __construct(int $cid) { $this->cid = $cid; }
      public function set(string $k, mixed $v = NULL): void {}
      public function get(string $k): mixed { return $k === 'cid' ? $this->cid : NULL; }
      public function setDestination(string $url): void { $this->_destination = $url; }
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

  private function runFormSubmissionCreatesContract(string $paymentOption, mixed $paymentAmount): void {
    $cid = $this->contact['id'];
    $form = new CreateForm();

    /** @phpstan-ignore-next-line */
    $form->controller = new class($cid) {
      public ?string $_destination = NULL;
      private int $cid;
      public function __construct(int $cid) { $this->cid = $cid; }
      public function set(string $k, mixed $v = NULL): void {}
      public function get(string $k): mixed { return $k === 'cid' ? $this->cid : NULL; }
      public function setDestination(string $url): void { $this->_destination = $url; }
    };

    /** @phpstan-ignore-next-line */
    $form->set('cid', $this->contact['id']);
    /** @phpstan-ignore-next-line */
    $form->preProcess();
    $form->buildQuickForm();

    $submissionValues = [
      'payment_option' => $paymentOption,
      'join_date'      => date('Y-m-d'),
      'end_date'       => date('Y-m-d'),
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

  public function testFormValidationWithValidData_create_null(): void {
    $this->runFormValidationWithValidData('create', NULL);
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
