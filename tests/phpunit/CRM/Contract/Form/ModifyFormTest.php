<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Modify as ModifyForm;

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
  protected array $contract = [];

  public function setUp(): void {
    parent::setUp();
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

    /** @phpstan-ignore-next-line */
    $membershipTypeResult = civicrm_api4('MembershipType', 'create', [
      'values' => [
        'name' => 'Modify Membership Type',
        'member_of_contact_id' => $this->contact['id'],
        'financial_type_id' => 2,
        'duration_unit' => 'year',
        'duration_interval' => 1,
        'period_type' => 'rolling',
        'is_active' => 1,
      ],
    ]);

    $this->membershipType = $membershipTypeResult[0];

    $this->contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '10.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
    ]);
  }

  public function testModifyFormValidation(): void {
    /** @phpstan-ignore-next-line */
    $_REQUEST['cid'] = (string) $this->contact['id'];
    /** @phpstan-ignore-next-line */
    $_REQUEST['id'] = (string) $this->contract['id'];
    /** @phpstan-ignore-next-line */
    $_REQUEST['modify_action'] = 'update';

    $form = new class() extends ModifyForm {
      /** @var array<string, mixed> */
      public $_submitValues = [];

      /**
       * @param array<string>|null $elementList
       * @param bool $filterInternal
       * @return array<string, mixed>
       */
      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }

    };

    /** @phpstan-ignore-next-line */
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

    /** @phpstan-ignore-next-line */
    $form->set('id', $this->contract['id']);
    /** @phpstan-ignore-next-line */
    $form->set('cid', $this->contact['id']);
    /** @phpstan-ignore-next-line */
    $form->set('contract_id', $this->contract['id']);

    $form->preProcess();

    $form->_submitValues = [
      'payment_option' => 'modify',
      'payment_amount' => '150',
      'payment_frequency' => '12',
      'iban' => 'DE89370400440532013000',
      'bic' => 'DEUTDEFF',
      'activity_date_time' => date('H:i:s'),
      '_qf_ModifyForm_next' => '1',
    ];

    self::assertTrue($form->validate());
  }

  public function testFormSubmissionModifyContract(): void {
    $cid = $this->contact['id'];
    /** @phpstan-ignore-next-line */
    $_REQUEST['cid'] = (string) $cid;
    /** @phpstan-ignore-next-line */
    $_REQUEST['id'] = (string) $this->contract['id'];
    /** @phpstan-ignore-next-line */
    $_REQUEST['modify_action'] = 'cancel';

    $form = new class() extends ModifyForm {
      /** @var array<string, mixed> */
      public $_submitValues = [];

      /**
       * @param array<string>|null $elementList
       * @param bool $filterInternal
       * @return array<string, mixed>
       */
      public function exportValues($elementList = NULL, $filterInternal = FALSE): array {
        return $this->_submitValues;
      }

    };

    /** @phpstan-ignore-next-line */
    $form->controller = new class((int) $cid, (int) $this->contract['id']) {
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

    /** @phpstan-ignore-next-line */
    $form->set('cid', $cid);
    $form->preProcess();
    $form->buildQuickForm();

    $submissionValues = [
      'payment_option' => 'RCUR',
      'join_date'      => date('Y-m-d'),
      'end_date'       => date('Y-m-d'),
      'activity_date_time' => date('H:i:s'),
      'activity_date' => date('Y-m-d'),
      'membership_dialoger' => '',
      'activity_details' => '',
      'activity_medium' => '',
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
      'cancel_reason' => 'Unknown',
    ];

    $form->_submitValues = $submissionValues;
    $form->setDefaults($submissionValues);
    $form->postProcess();

    /** @phpstan-ignore-next-line */
    $contracts = civicrm_api3('Contract', 'get', [
      'contact_id' => $cid,
    ]);

    self::assertEquals(0, $contracts['count']);
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
