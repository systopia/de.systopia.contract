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

    $this->contract = $this->createNewContract([
      'contact_id' => $this->contact['id'],
      'is_sepa' => 1,
      'amount' => '10.00',
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      'membership_contract' => 'TEST-001',
      'membership_reference' => 'REF-001',
    ]);

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

    $this->mandate = SepaMandate::create(TRUE)
      ->addValue('contact_id', $contact['id'])
      ->addValue('type', 'RCUR')
      ->addValue('entity_table', 'civicrm_contribution_recur')
      ->addValue('entity_id', $recurContriId)
      ->addValue('reference', 'TEST-MANDATE-001')
      ->addValue('date', '2025-05-01 13:00:00')
      ->addValue('iban', 'DE12500105170648489890')
      ->addValue('bic', 'INGDDEFFXXX')
      ->addValue('creditor_id', $creditor['id'])
      ->addValue('status', 'INIT')
      ->execute()
      ->first();

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

  /**
   * @return array<string, array{0: string}>
   */
  public function modifyActionProvider(): array {
    return [
      'cancel' => ['cancel'],
      'update' => ['update'],
      'pause' => ['pause'],
    ];
  }

  /**
   * @dataProvider modifyActionProvider
   */
  public function testFormSubmissionModifyContract(
    string $modifyAction
  ): void {
    $cid = $this->contact['id'];
    $contractId = $this->contract['id'];
    /** @phpstan-ignore-next-line */
    $_REQUEST['cid'] = (string) $cid;
    /** @phpstan-ignore-next-line */
    $_REQUEST['id'] = (string) $contractId;
    /** @phpstan-ignore-next-line */
    $_REQUEST['modify_action'] = $modifyAction;

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

    $submissionValues = $this->getModifyActionSubmissionValues($modifyAction);
    $form->_submitValues = $submissionValues;
    $form->setDefaults($submissionValues);
    $form->postProcess();

    /** @phpstan-ignore-next-line */
    $result = civicrm_api3('Contract', 'get', ['contact_id' => $cid]);
    $contract = $this->getContract($result['id']);

    $expectedStatus = match ($modifyAction) {
      'update' => 'Current',
      'cancel' => 'Cancelled',
      'pause' => 'Paused',
      default => throw new \RuntimeException("Unhandled modifyAction: $modifyAction"),
    };

    self::assertEquals($this->getMembershipStatusID($expectedStatus), $contract['status_id']);

    if ($modifyAction === 'update') {
      self::assertEquals($this->contact['id'], $contract['contact_id']);
      self::assertEquals(6, $contract['membership_payment.membership_frequency']);
    }
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  private function getModifyActionSubmissionValues(string $modifyAction): array {
    $base = [
      'payment_option' => 'RCUR',
      'membership_type_id' => $this->membershipType['id'],
      'campaign_id' => $this->campaign['id'] ?? NULL,
      'account_holder' => $this->contact['display_name'],
      'activity_details' => '',
      'activity_medium' => '',
      'membership_contract' => 'UPDATE TEST-001',
      'membership_reference' => 'UPDATE REF-001',
      'activity_date_time' => date('H:i:s'),
      'activity_date' => date('Y-m-d'),
    ];

    return match ($modifyAction) {
      'update' => $base + [
        'join_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d'),
        'membership_dialoger' => '',
        'cycle_day' => '30',
        'payment_amount' => '120',
        'payment_frequency' => '6',
        'iban' => 'DE89370400440532013000',
        'bic' => 'DEUTDEFF',
        'start_date' => date('Y-m-d'),
      ],
      'pause' => $base + [
        'resume_date' => date('Y-m-d', strtotime('+1 day')),
      ],
      'cancel' => $base + [
        'cancel_reason' => 'Unknown',
      ],
      default => throw new \RuntimeException("Unhandled modifyAction: $modifyAction"),
    };
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
