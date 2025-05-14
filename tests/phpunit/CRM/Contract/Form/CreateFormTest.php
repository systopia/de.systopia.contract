<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Create as CreateForm;

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
    parent::setUp();
    $this->setupRecurContributionStatus();
    $this->createRequiredEntities();
  }

  private function createRequiredEntities(): void {
    $contact = $this->createContactWithRandomEmail();

    /** @phpstan-ignore-next-line */
    $contactResult = civicrm_api4('Contact', 'get', [
      'where' => [['id', '=', $contact['id']]],
      'limit' => 1,
    ]);

    if ($contactResult->count() === 0) {
      throw new \RuntimeException('Contact not found');
    }

    $this->contact = $contactResult[0];

    try {
      /** @phpstan-ignore-next-line */
      $group = civicrm_api4('OptionGroup', 'get', [
        'where' => [['name', '=', 'campaign_type']],
      ]);
      $optionGroupId = $group[0]['id'];

      /** @phpstan-ignore-next-line */
      $existing = civicrm_api4('OptionValue', 'get', [
        'where' => [
          ['option_group_id', '=', $optionGroupId],
          ['name', '=', 'test_campaign_type'],
        ],
      ]);

      if ($existing->count() === 0) {
        /** @phpstan-ignore-next-line */
        civicrm_api4('OptionValue', 'create', [
          'values' => [
            'option_group_id' => $optionGroupId,
            'label' => 'Test Campaign Type',
            'name' => 'test_campaign_type',
            'is_active' => 1,
          ],
        ]);
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

      /** @phpstan-ignore-next-line */
      $membershipGeneralGroup = civicrm_api4('CustomGroup', 'get', [
        'where' => [['name', '=', 'membership_general']],
      ]);

      if ($membershipGeneralGroup->count() === 0) {
        /** @phpstan-ignore-next-line */
        $membershipGeneralGroup = civicrm_api4('CustomGroup', 'create', [
          'values' => [
            'title' => 'Membership General',
            'name' => 'membership_general',
            'extends' => 'Membership',
            'is_active' => 1,
            'style' => 'Inline',
          ],
        ]);
        $groupId = $membershipGeneralGroup[0]['id'];
      }
      else {
        $groupId = $membershipGeneralGroup[0]['id'];
      }

      /** @phpstan-ignore-next-line */
      $membershipNotesField = civicrm_api4('CustomField', 'get', [
        'where' => [
          ['custom_group_id', '=', $groupId],
          ['name', '=', 'membership_notes'],
        ],
      ]);

      if ($membershipNotesField->count() === 0) {
        /** @phpstan-ignore-next-line */
        civicrm_api4('CustomField', 'create', [
          'values' => [
            'custom_group_id' => $groupId,
            'label' => 'Membership Notes',
            'name' => 'membership_notes',
            'data_type' => 'Memo',
            'html_type' => 'TextArea',
            'is_active' => 1,
            'is_searchable' => 1,
          ],
        ]);
      }

    }
    catch (Exception $e) {
      throw $e;
    }

    /** @phpstan-ignore-next-line */
    $membershipType = civicrm_api4('MembershipType', 'create', [
      'values' => [
        'name' => 'Test Membership Type',
        'member_of_contact_id' => $this->contact['id'],
        'financial_type_id' => 2,
        'duration_unit' => 'year',
        'duration_interval' => 1,
        'period_type' => 'rolling',
        'is_active' => 1,
      ],
    ]);

    $this->membershipType = $membershipType[0];
  }

  private function setupRecurContributionStatus(): void {
    try {
      /** @phpstan-ignore-next-line */
      $optionGroupResult = civicrm_api4('OptionGroup', 'get', [
        'where' => [['name', '=', 'contribution_status']],
      ]);

      if ($optionGroupResult->count() === 0) {
        /** @phpstan-ignore-next-line */
        $optionGroupResult = civicrm_api4('OptionGroup', 'create', [
          'values' => [
            'name' => 'contribution_status',
            'title' => 'Contribution Status',
            'is_active' => 1,
          ],
        ]);
      }

      $optionGroupId = $optionGroupResult[0]['id'];

      /** @phpstan-ignore-next-line */
      $status = civicrm_api4('OptionValue', 'get', [
        'where' => [
          ['option_group_id', '=', $optionGroupId],
          ['name', '=', 'In Progress'],
          ['is_active', '=', 1],
        ],
      ]);

      if ($status->count() === 0) {
        /** @phpstan-ignore-next-line */
        civicrm_api4('OptionValue', 'create', [
          'values' => [
            'option_group_id' => $optionGroupId,
            'label' => 'In Progress',
            'name' => 'In Progress',
            'value' => 5,
            'is_active' => 1,
            'is_reserved' => 1,
          ],
        ]);
        $this->recurContributionStatusId = '5';
      }
      else {
        $first = $status[0];
        $this->recurContributionStatusId = $first['value'];
      }

      /** @phpstan-ignore-next-line */
      $completedStatus = civicrm_api4('OptionValue', 'get', [
        'where' => [
          ['option_group_id', '=', $optionGroupId],
          ['name', '=', 'Completed'],
        ],
      ]);

      if ($completedStatus->count() === 0) {
        /** @phpstan-ignore-next-line */
        civicrm_api4('OptionValue', 'create', [
          'values' => [
            'option_group_id' => $optionGroupId,
            'label' => 'Completed',
            'name' => 'Completed',
            'value' => 1,
            'is_active' => 1,
            'is_default' => 1,
          ],
        ]);
      }
    }
    catch (Exception $e) {
      /** @phpstan-ignore-next-line */
      throw new CRM_Core_Exception($e->getMessage(), 0, $e);
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
      /** @phpstan-ignore-next-line */
      civicrm_api4('Contact', 'update', [
        'values' => [
          'id' => $this->contact['id'],
          'is_deleted' => 1,
        ],
      ]);
    }
    catch (Exception $e) {
      throw $e;
    }

    if (isset($this->campaign['id']) && $this->campaign['id'] !== 0) {
      /** @phpstan-ignore-next-line */
      civicrm_api3('Campaign', 'delete', ['id' => $this->campaign['id']]);
    }

    if (isset($this->membershipType['id']) && $this->membershipType['id'] !== 0) {
      /** @phpstan-ignore-next-line */
      civicrm_api4('MembershipType', 'delete', [
        'where' => [
          ['id', '=', $this->membershipType['id']],
        ],
      ]);
    }

    parent::tearDown();
  }

}
