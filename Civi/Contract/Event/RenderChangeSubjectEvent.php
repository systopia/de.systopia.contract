<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract\Event;

use Civi\Contract\ContractChange\ContractChangeInterface;
use Civi\Contract\ContractChange\SchedulableContractChangeInterface;
use CRM_Contract_ExtensionUtil as E;
use CRM_Contract_CustomData as CRM_Contract_CustomData;

/**
 * Class RenderChangeSubjectEvent
 *
 * @note  currently, this doesn't work during the creation of the change activities,
 *   because the symfony events cause havoc there
 *
 * Allows extensions to provide a custom renderer for
 *  the subjects of change events
 *
 * @package Civi\Contract\Event
 */
class RenderChangeSubjectEvent extends AbstractConfigurationEvent {
  public const EVENT_NAME = 'de.contract.renderchangesubject';

  /**
   * @var string|null the raw contract data after
   */
  protected ?string $subject = NULL;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param array<string, mixed>|null $contract_data_before
   *   the state of the contract before the change
   *
   * @param array<string, mixed>|null $contract_data_after
   *   the state of the contract after the change
   */
  public function __construct(
    private readonly ContractChangeInterface $change,
    protected ?array $contract_data_before,
    protected ?array $contract_data_after
  ) {
    $this->subject = NULL;
    if (NULL !== $this->contract_data_before) {
      // @phpstan-ignore assign.propertyType
      CRM_Contract_CustomData::labelCustomFields($this->contract_data_before);
    }
    if (NULL !== $this->contract_data_after) {
      // @phpstan-ignore assign.propertyType
      CRM_Contract_CustomData::labelCustomFields($this->contract_data_after);
    }
  }

  /**
   * Issue a Symfony event to render a contract change's subject/title
   *
   * @param array<string, mixed>|null $contract_data_before
   *   the state of the contract before the change
   *
   * @param array<string, mixed>|null $contract_data_after
   *   the state of the contract after the change
   *
   * @return string|null
   *   the subject line of the given change activity
   */
  public static function renderCustomChangeSubject(
    ContractChangeInterface $change,
    ?array $contract_data_before,
    ?array $contract_data_after
  ): ?string {
    // create and run event
    $event = new RenderChangeSubjectEvent($change, $contract_data_before, $contract_data_after);
    \Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);

    $custom_subject = $event->getRenderedSubject();
    return $custom_subject;
  }

  /**
   * Set/override the subject for the change activity
   *
   * @param string $subject
   *    the proposed subject for the change
   */
  public function setRenderedSubject(string $subject): void {
    $this->subject = $subject;
  }

  /**
   * Get the currently proposed subject
   *
   * @return string|null
   *   the proposed subject for the change
   */
  public function getRenderedSubject(): ?string {
    return $this->subject;
  }

  /**
   * Get the contract data before this change
   *
   * @param null|string $attribute
   *   if attribute name is given, the attribute is returned
   *
   * @return mixed
   *   raw contract data before the change
   */
  public function getContractDataBefore(?string $attribute = NULL): mixed {
    if ($attribute) {
      return $this->contract_data_before[$attribute] ?? NULL;
    }
    else {
      return $this->contract_data_before;
    }
  }

  /**
   * Get the contract data after this change
   *
   * @param null|string $attribute
   *   if attribute name is given, the attribute is returned
   *
   * @return mixed
   *   raw contract data after the change
   */
  public function getContractDataAfter(?string $attribute = NULL): mixed {
    if ($attribute) {
      return $this->contract_data_after[$attribute] ?? NULL;
    }
    else {
      return $this->contract_data_after;
    }
  }

  /**
   * Get a value from the data provided. It will first be taken from
   *   the *after* data, but if it doesn't contain any information,
   *   it'll use the *before* data for the lookup
   *
   * @param string $attribute_name
   *   attribute name
   *
   * @return mixed|null
   *   the value
   */
  public function getContractAttribute(string $attribute_name): mixed {
    return $this->contract_data_after[$attribute_name]
        ?? \CRM_Utils_Request::retrieve($attribute_name, 'String')
        ?? $this->contract_data_before[$attribute_name]
        ?? NULL;
  }

  /**
   * Get a value from the data provided. It will first be taken from
   *   the *after* data, but if it doesn't contain any information,
   *   it'll use the *before* data for the lookup
   *
   * @param string $attribute_name
   *   attribute name
   *
   * @return mixed|null
   *   the value
   */
  public function getChangeAttribute(string $attribute_name): mixed {
    // this is all mixed up in the same pile
    return $this->getContractAttribute($attribute_name);
  }

  /**
   * Get the action name of the change
   *
   * @return string
   */
  public function getActivityAction(): ?string {
    return $this->change instanceof SchedulableContractChangeInterface ? $this->change::getActionName() : NULL;
  }

  public function getActivityTypeName(): string {
    return $this->change::getActivityTypeName();
  }

  /**
   * @return string label of the membership type
   */
  public function getMembershipTypeName(): string {
    $type_id = $this->getContractAttribute('membership_type_id');
    if (!empty($type_id)) {
      return \CRM_Contract_Utils::lookupValue('MembershipType', 'name', ['id' => $type_id]);
    }
    else {
      return E::ts('(not found)');
    }
  }

  /**
   * @return string label of the cancel reason
   */
  public function getCancelReason(): string {
    $reason_id = $this->getChangeAttribute('contract_cancellation.contact_history_cancel_reason');
    if (empty($reason_id)) {
      $reason_id = $this->getContractAttribute('membership_cancellation.membership_cancel_reason');
    }

    if (!empty($reason_id)) {
      return \CRM_Contract_Utils::lookupOptionValue('contract_cancel_reason', $reason_id);
    }
    else {
      return E::ts('(not found)');
    }
  }

  /**
   *
   * @return float annual amount
   */
  public function getMembershipAnnualAmount(): float {
    $new_amount = (float) $this->getChangeAttribute('contract_updates.ch_annual');
    if (empty($new_amount)) {
      $new_amount = (float) $this->getContractAttribute('membership_payment.membership_annual');
    }
    return $new_amount;
  }

  /**
   * @return float annual amount
   */
  public function getMembershipIncreaseAmount(): float {
    $value = $this->getChangeAttribute('contract_updates.ch_annual_diff');
    if (!$value) {
      // no diff recorded, try to calculate
      $before = (float) \CRM_Contract_SepaLogic::formatMoney(
        $this->getContractDataBefore('membership_payment.membership_annual')
      );
      $after = (float) \CRM_Contract_SepaLogic::formatMoney(
        $this->getContractDataAfter('membership_payment.membership_annual')
      );
      $value = $after - $before;
    }
    return (float) $value;
  }

  /**
   * @return string rendered
   */
  public function getExecutionDate(): string {
    $date = $this->getChangeAttribute('activity_date_time');
    if ($date) {
      return date('Y-m-d', strtotime($date));
    }
    else {
      return 'n/a';
    }
  }

  /**
   * @return string label of the frequency
   */
  public function getMembershipPaymentFrequency(): string {
    $frequency = (int) $this->getChangeAttribute('contract_updates.ch_frequency');
    if (empty($frequency)) {
      $frequency = (int) $this->getContractAttribute('membership_payment.membership_frequency');
    }
    /** @var string */
    return \CRM_Contract_Utils::lookupOptionValue('payment_frequency', (string) $frequency);
  }

}
