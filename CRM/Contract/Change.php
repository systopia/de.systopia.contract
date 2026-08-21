<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Api4\Activity;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Membership;
use Civi\Contract\Api4\Helper\FieldNameHelper;
use Civi\Contract\ContractChange\ContractChangeInterface;
use Civi\Contract\Event\RenderChangeSubjectEvent;

/**
 * Base class for contract changes. These are tracked changes to
 *  a contract, represented by an activity
 *
 * This new 'Change' concept is the replacement for the CRM_Contract_ModificationActivity
 *  and the CRM_Contract_Handlers
 *
 * Note: When data was fetched via APIv3 integers might be given as numeric
 * strings.
 * @phpstan-type changeT array{
 *   id?: int,
 *   "activity_type_id:name"?: string,
 *   activity_type_id?: int,
 *   activity_date_time?: string,
 *   campaign_id?: int,
 *   membership_type_id?: int,
 *   status_id?: int|string,
 *   "contract_activity.contract_id"?: int,
 *   "membership_payment.cycle_day"?: int,
 *   "membership_payment.defer_payment_start"?: int,
 *   "membership_payment.from_name"?: string,
 *   "membership_payment.from_ba"?: string,
 *   "membership_payment.to_ba"?: string,
 *   "membership_payment.membership_annual"?: float,
 *   "membership_payment.membership_frequency"?: int,
 *   "membership_payment.membership_recurring_contribution"?: int,
 *   "membership_payment.payment_instrument"?: int,
 *   "membership_cancellation.membership_cancel_reason"?: string,
 *   ...
 *  }
 */
// phpcs:disable Generic.NamingConventions.AbstractClassNamePrefix.Missing
abstract class CRM_Contract_Change implements ContractChangeInterface {
// phpcs:enable

  /**
   * @phpstan-var changeT
   */
  protected array $data;

  /**
   * @phpstan-var array<string, mixed>
   * Contract data (cached)
   */
  protected ?array $contract = NULL;

  /**
   * Maps the contract fields to the change activity fields
   */
  protected const FIELD_MAPPING_CHANGE_CONTRACT = [
    'membership_type_id'                                   => 'contract_updates.ch_membership_type',
    'campaign_id'                                          => 'contract_updates.ch_campaign_id',
    'membership_payment.membership_recurring_contribution' => 'contract_updates.ch_recurring_contribution',
    'membership_payment.payment_instrument'                => 'contract_updates.ch_payment_instrument',
    'membership_payment.membership_annual'                 => 'contract_updates.ch_annual',
    'membership_payment.membership_frequency'              => 'contract_updates.ch_frequency',
    'membership_payment.from_ba'                           => 'contract_updates.ch_from_ba',
    'membership_payment.to_ba'                             => 'contract_updates.ch_to_ba',
    'membership_payment.cycle_day'                         => 'contract_updates.ch_cycle_day',
    'membership_payment.defer_payment_start'               => 'contract_updates.ch_defer_payment_start',
    'membership_payment.from_name'                         => 'contract_updates.ch_from_name',
  ];

  /**
   * @phpstan-param changeT $data
   */
  public function __construct(array $data) {
    $this->data = $data;
    $this->data['activity_type_id:name'] = $this::getActivityTypeName();
  }

  /**
   * Get the change ID
   */
  public function getID(): ?int {
    return $this->data['id'] ?? NULL;
  }

  /**
   * Get the contract ID
   */
  public function getContractID(): int {
    if (isset($this->data['contract_activity.contract_id'])) {
      $contractId = $this->data['contract_activity.contract_id'];
    }
    else {
      // Lookup for APIv3 custom field name.
      $contractReferenceFieldId = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id:name', '=', 'contract_activity')
        ->addWhere('name', '=', 'contract_id')
        ->execute()
        ->single()['id'];
      $contractId = $this->data['custom_' . $contractReferenceFieldId] ?? NULL;
    }

    if (NULL === $contractId) {
      throw new RuntimeException('Contract ID not fond');
    }

    return (int) $contractId;
  }

  /**
   * Derive/populate additional data
   */
  public function populateData(): void {
    // populate parameters
    $contract = $this->getContract(TRUE);

    // propagate derived fields
    foreach (CRM_Contract_Change::FIELD_MAPPING_CHANGE_CONTRACT as $contract_attribute => $change_attribute) {
      if (empty($this->data[$change_attribute])) {
        $this->data[$change_attribute] = $contract[$contract_attribute] ?? '';
      }
    }

    if (empty($this->data['subject'])) {
      // add default subject
      $this->setParameter('subject', $this->getSubject($contract, NULL));
    }
  }

  /**
   * @inheritDoc
   */
  public function getContract(bool $withPaymentData = FALSE): array {
    $contractId = $this->getContractID();
    if ($this->contract === NULL || (int) $this->contract['id'] !== $contractId) {
      // (re)load contract
      try {
        $this->contract = Membership::get(FALSE)
          ->addSelect('*', 'custom.*')
          ->addWhere('id', '=', $contractId)
          ->execute()
          ->single();
      }
      catch (Exception $ex) {
        throw new \RuntimeException("Contract [{$contractId}] not found!", $ex->getCode(), $ex);
      }
    }

    // add the payment data, if requested
    if ($withPaymentData) {
      if (!isset($this->contract['membership_payment.membership_frequency'])) {
        $this->derivePaymentData($this->contract);
      }
    }

    // @phpstan-ignore return.type (phpstan assumes that contract might be NULL)
    return $this->contract;
  }

  /**
   * Enrich the given contact with payment data
   *
   * @param array<string, mixed> $contract contract data
   */
  public function derivePaymentData(array &$contract): void {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      // we have a recurring contribution!
      try {
        /**
         * @var array{
         *   id: int,
         *   amount: float,
         *   frequency_unit: "year"|"month"|null,
         *   frequency_interval: int,
         *   cycle_day: int,
         *   payment_instrument_id: int|null,
         * } $contributionRecur
         */
        $contributionRecur = ContributionRecur::get(FALSE)
          ->addWhere('id', '=', $contract['membership_payment.membership_recurring_contribution'])
          ->addSelect('id', 'amount', 'frequency_unit', 'frequency_interval', 'cycle_day', 'payment_instrument_id')
          ->execute()
          ->single();
        $contract['membership_payment.membership_annual']    = $this->calcAnnualAmount($contributionRecur);
        $contract['membership_payment.membership_frequency'] = $this->calcPaymentFrequency($contributionRecur);
        $contract['membership_payment.cycle_day']            = $contributionRecur['cycle_day'];
        $contract['membership_payment.payment_instrument']   = $contributionRecur['payment_instrument_id'] ?? NULL;

        // if this is a sepa payment, get the 'to' and 'from' bank account
        /** @var array{count: int, id: int, values: array<int, array<string, mixed>>} $sepaMandateResult */
        $sepaMandateResult = civicrm_api3('SepaMandate', 'get', [
          'entity_table' => 'civicrm_contribution_recur',
          'entity_id'    => $contributionRecur['id'],
        ]);
        if (1 === $sepaMandateResult['count']) {
          $sepaMandate = $sepaMandateResult['values'][$sepaMandateResult['id']];
          $contract['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount(
            $sepaMandate['contact_id'],
            $sepaMandate['iban'],
            $sepaMandate['bic']
          );
          $contract['membership_payment.to_ba']   = CRM_Contract_BankingLogic::getCreditorBankAccount();
          $contract['membership_payment.from_name'] = $sepaMandate['account_holder'] ?? '';

        }
        elseif (0 === $sepaMandateResult['count']) {
          // this should be a recurring contribution -> get from the latest contribution
          [
            $from_ba,
            $to_ba,
          ] = CRM_Contract_BankingLogic::getAccountsFromRecurringContribution(
            $contributionRecur['id']
          );
          $contract['membership_payment.from_ba'] = $from_ba;
          $contract['membership_payment.to_ba']   = $to_ba;

        }
        else {
          // this is an error:
          $contract['membership_payment.from_ba'] = '';
          $contract['membership_payment.to_ba']   = '';
          $contract['membership_payment.from_name']   = '';

        }
      }
      catch (Exception $ex) {
        Civi::log()->debug(
          "Couldn't load recurring contribution [{$contract['membership_payment.membership_recurring_contribution']}]"
        );
      }
    }
  }

  /**
   * Update the contract with the given data
   *
   * @param $updates array changes: attribute->value
   * @throws Exception
   */
  public function updateContract(array $updates): void {
    // make sure the ID is there
    $updates['id'] = $this->getContractID();

    // derive fields if possible
    $this->derivePaymentData($updates);

    // finally: write through
    Membership::update(FALSE)->setValues($updates)->execute();

    // and delete the cached contract data (if any)
    $this->contract = NULL;
  }

  /**
   * @inheritDoc
   */
  final public function getSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    return RenderChangeSubjectEvent::renderCustomChangeSubject(
      $this,
      $contractBefore,
      $contractAfter
    ) ?? $this->renderSubject($contractAfter, $contractBefore);
  }

  /**
   * @phpstan-param array<string, mixed>|null $contractAfter
   *   Data of the contract after the change.
   * @phpstan-param array<string, mixed>|null $contractBefore
   *   Data of the contract before the change.
   */
  abstract protected function renderSubject(?array $contractAfter, ?array $contractBefore): string;

  /**
   * Calculate annual amount
   *
   * @param array{amount: float, frequency_unit: "month"|"year"|null, frequency_interval: int, ...} $contributionRecur
   *    recurring contribution data
   * @return float
   */
  protected function calcAnnualAmount(array $contributionRecur): float {
    return round($contributionRecur['amount'] * $this->calcPaymentFrequency($contributionRecur), 2);
  }

  /**
   * Calculate the frequency from the unit/interval set in the recurring contribution data
   * @param array{frequency_interval: int, frequency_unit: "year"|"month"|null, ...} $contributionRecur
   *    recurring contribution data
   * @return int payment frequency (in months)
   * @throws Exception if the unit is not recognised ('month' or 'year')
   */
  protected function calcPaymentFrequency(array $contributionRecur) {
    if (empty($contributionRecur['frequency_interval'])) {
      // unable to calculate
      return 0;
    }

    if ('year' === $contributionRecur['frequency_unit']) {
      assert(1 === $contributionRecur['frequency_interval']);

      return 1 / $contributionRecur['frequency_interval'];
    }
    // @phpstan-ignore voku.Identical
    elseif ('month' === ($contributionRecur['frequency_unit'] ?? 'month')) {
      assert(in_array($contributionRecur['frequency_interval'], [1, 2, 3, 4, 6, 12], TRUE));

      return 12 / $contributionRecur['frequency_interval'];
    }
    else {
      throw new \RuntimeException("Frequency unit '{$contributionRecur['frequency_unit']}' not allowed.");
    }
  }

  /**
   * @inheritDoc
   */
  public function setParameter(string $key, mixed $value): void {
    // @phpstan-ignore assign.propertyType
    $this->data[$key] = $value;
  }

  /**
   * @inheritDoc
   */
  public function getParameter(string $key, mixed $default = NULL): mixed {
    return $this->data[$key]
      ?? CRM_Utils_Request::retrieve($key, 'String')
      ?? $default;
  }

  /**
   * Save data to the DB (activity)
   */
  public function save(): void {
    // make sure all custom fields are transformed into the 'custom_[id]' notation
    $mitigation_ch_defer_payment_start_value = $this->data['membership_payment.defer_payment_start'] ?? 0;

    // store via API
    $data = $this->data;
    // Using Membership custom fields in Activity will result in wrong database
    // inserts.
    $fieldNames = (new FieldNameHelper())->getFieldNames('Activity');
    $data = array_intersect_key($data, $fieldNames);

    // APIv3 allowed the name for status_id. This is for compatibility.
    // Can be removed once fully migrated to APIv4.
    if (isset($data['status_id']) && !is_numeric($data['status_id'])) {
      $data['status_id:name'] = $data['status_id'];
      unset($data['status_id']);
    }

    $result = Activity::save(FALSE)->setRecords([$data])->execute()->single();

    // mitigation: there seems to be cases where the boolean value will not be written to ch_defer_payment_start
    // todo: extract table/column name from specs? Should be identical...
    CRM_Core_DAO::singleValueQuery(
        'UPDATE civicrm_value_contract_updates SET ch_defer_payment_start = %1 WHERE entity_id = %2',
        [
          1 => [$mitigation_ch_defer_payment_start_value, 'Int'],
          2 => [$result['id'], 'Int'],
        ]
    );

    // make sure we store the activity ID (if this is the first time)
    if (empty($this->data['id'])) {
      $this->data['id'] = $result['id'];
    }
  }

  /**
   * Check if this change is new, i.e. has not yet been saved to the DB
   */
  public function isNew(): bool {
    return !isset($this->data['id']);
  }

  /**
   * Set change status
   *
   * @param string $status valid activity status
   */
  public function setStatus(string $status): void {
    $this->data['status_id'] = $status;
  }

  /**
   * Cached query for API lookups
   *
   * @param $entity    string entity
   * @param $query     array query options
   * @param $attribute string attribute having the desired value
   * @return mixed value
   */
  protected function lookupValue($entity, $attribute, $query) {
    return CRM_Contract_Utils::lookupValue($entity, $attribute, $query);
  }

  /**
   * Provide a universal function to label a internal ID with the corresponding label where applicable
   *
   * @param $value       string current value
   * @param $field_name  string field this value is from
   * @return string      string labelled value
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function labelValue($value, $field_name) {
  // phpcs:enable
    switch ($field_name) {
      case 'membership_type_id':
      case 'contract_updates.ch_membership_type':
        if (is_numeric($value)) {
          return $this->lookupValue('MembershipType', 'name', ['id' => $value]);
        }
        else {
          return $value;
        }

      case 'membership_payment.membership_annual':
        /** @var \Civi\Core\Format $format */
        $format = Civi::service('format');
        return $format->money($value);

      case 'membership_payment.membership_frequency':
      case 'contract_updates.ch_frequency':
        if (is_numeric($value)) {
          return $this->lookupValue(
            'OptionValue',
            'label',
            ['value' => $value, 'option_group_id' => 'payment_frequency']
          );
        }
        else {
          return $value;
        }

      case 'membership_payment.from_ba':
      case 'contract_updates.ch_from_ba':
      case 'membership_payment.to_ba':
      case 'contract_updates.ch_to_ba':
        if (is_numeric($value)) {
          return CRM_Contract_BankingLogic::getIBANforBankAccount($value);
        }
        else {
          return $value;
        }

      case 'membership_payment.payment_instrument':
      case 'contract_updates.ch_payment_instrument':
        if (is_numeric($value)) {
          return $this->lookupValue(
            'OptionValue',
            'label',
            ['value' => $value, 'option_group_id' => 'payment_instrument']
          );
        }
        else {
          return $value;
        }

      case 'membership_cancellation.membership_cancel_reason':
      case 'contract_cancellation.contact_history_cancel_reason':
        if (is_numeric($value)) {
          return $this->lookupValue(
            'OptionValue',
            'label',
            ['value' => $value, 'option_group_id' => 'contract_cancel_reason']
          );
        }
        else {
          return $value;
        }

      default:
        return $value;
    }
  }

  /**
   * Provide a universal function to resolve the identity of a label
   *
   * @param $value       string current label
   * @param $field_name  string field this value is from
   * @return string      string labelled value
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  protected function resolveValue($value, $field_name) {
  // phpcs:enable
    switch ($field_name) {
      case 'membership_type_id':
      case 'contract_updates.ch_membership_type':
        if (is_numeric($value)) {
          return $value;
        }
        else {
          return $this->lookupValue('MembershipType', 'id', ['name' => $value]);
        }

      case 'membership_payment.membership_frequency':
      case 'contract_updates.ch_frequency':
        if (is_numeric($value)) {
          return $value;
        }
        else {
          return $this->lookupValue(
            'OptionValue',
            'value',
            ['label' => $value, 'option_group_id' => 'payment_frequency']
          );
        }

      case 'membership_payment.from_ba':
      case 'contract_updates.ch_from_ba':
      case 'membership_payment.to_ba':
      case 'contract_updates.ch_to_ba':
        if (is_numeric($value)) {
          return $value;
        }
        else {
          return 'ERROR, cannot resolve bank account by IBAN';
        }

      case 'membership_payment.payment_instrument':
      case 'contract_updates.ch_payment_instrument':
        if (is_numeric($value)) {
          return $value;
        }
        else {
          return $this->lookupValue(
            'OptionValue',
            'value',
            ['label' => $value, 'option_group_id' => 'payment_instrument']
          );
        }

      case 'membership_cancellation.membership_cancel_reason':
      case 'contract_cancellation.contact_history_cancel_reason':
        if (is_numeric($value)) {
          return $value;
        }
        else {
          return $this->lookupValue(
            'OptionValue',
            'value',
            ['label' => $value, 'option_group_id' => 'contract_cancel_reason']
          );
        }

      default:
        return $value;
    }
  }

}
