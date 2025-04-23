<?php

declare(strict_types = 1);

use CRM_Contract_ContractTestBase as ContractTestBase;
use CRM_Contract_Form_Modify as ModifyForm;

/**
 * @group headless
 */
class ModifyFormTest extends ContractTestBase {

  /**
   * @var array{id: int, email: string, display_name: string} */
  protected array $contact = ['id' => 0, 'email' => '', 'display_name' => ''];

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
    $contactDetails = civicrm_api3('Contact', 'getsingle', [
      'id' => $contact['id'],
      'return' => ['id', 'email', 'display_name'],
    ]);

    $this->contact = [
      'id' => (int) $contactDetails['id'],
      'email' => $contactDetails['email'],
      'display_name' => $contactDetails['display_name'],
    ];

    /** @phpstan-ignore-next-line */
    $membershipType = civicrm_api3('MembershipType', 'create', [
      'name' => 'Modify Membership Type',
      'member_of_contact_id' => $this->contact['id'],
      'financial_type_id' => 2,
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'period_type' => 'rolling',
      'is_active' => 1,
    ]);

    $this->membershipType = $membershipType['values'][$membershipType['id']];

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

  public function tearDown(): void {
    try {
      /** @phpstan-ignore-next-line */
      civicrm_api3('Contact', 'create', [
        'id' => $this->contact['id'],
        'is_deleted' => 1,
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
      civicrm_api3('MembershipType', 'delete', ['id' => $this->membershipType['id']]);
    }

    parent::tearDown();
  }

}
