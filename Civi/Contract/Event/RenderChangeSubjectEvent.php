<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract\Event;

use Civi;
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
   * @var string the action name
   */
  protected $change_action;

  /**
   * @var array the raw contract data before
   */
  protected $contract_data_before;

  /**
   * @var array the raw contract data after
   */
  protected $contract_data_after;

  /**
   * @var array the data of the change object
   */
  protected $change_data;

  /**
   * @var string the raw contract data after
   */
  protected $subject;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param string $change_action
   *   the internal name of the change action
   *
   * @param array $contract_data_before
   *   the state of the contract before the change
   *
   * @param array $contract_data_after
   *   the state of the contract after the change
   */
  public function __construct($change_action, $contract_data_before, $contract_data_after) {
    $this->subject = NULL;
    $this->change_action = $change_action;
    $this->change_data = NULL;
    $this->contract_data_before = $contract_data_before;
    $this->contract_data_after = $contract_data_after;
    if ($this->contract_data_before) {
      CRM_Contract_CustomData::labelCustomFields($this->contract_data_before);
    }
    if ($this->contract_data_after) {
      CRM_Contract_CustomData::labelCustomFields($this->contract_data_after);
    }
  }

  /**
   * Issue a Symfony event to render a contract change's subject/title
   *
   * @param string $change_action
   *   the internal name of the change action
   *
   * @param array|null $contract_data_before
   *   the state of the contract before the change
   *
   * @param array|null $contract_data_after
   *   the state of the contract after the change
   *
   * @return string
   *   the subject line of the given change activity
   */
  public static function renderCustomChangeSubject($change_action, $contract_data_before, $contract_data_after) {
    // create and run event
    $event = new RenderChangeSubjectEvent($change_action, $contract_data_before, $contract_data_after);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);

    $custom_subject = $event->getRenderedSubject();
    return $custom_subject;
  }

  /**
   * Set/override the subject for the change activity
   *
   * @param string $subject
   *    the proposed subject for the change
   */
  public function setRenderedSubject($subject) {
    $this->subject = $subject;
  }

  /**
   * Get the currently proposed subject
   *
   * @return string
   *   the proposed subject for the change
   */
  public function getRenderedSubject() {
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
  public function getContractDataBefore($attribute = NULL) {
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
  public function getContractDataAfter($attribute = NULL) {
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
  public function getContractAttribute($attribute_name) {
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
  public function getChangeAttribute($attribute_name) {
    // this is all mixed up in the same pile
    return $this->getContractAttribute($attribute_name);
  }

  /**
   * Get the action name of the change
   *
   * @return string
   */
  public function getActivityAction() {
    return $this->change_action;
  }

  /**
   * @return string label of the membership type
   */
  public function getMembershipTypeName() {
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
  public function getCancelReason() {
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
  public function getMembershipAnnualAmount() {
    $new_amount = (float) $this->getChangeAttribute('contract_updates.ch_annual');
    if (empty($new_amount)) {
      $new_amount = (float) $this->getContractAttribute('membership_payment.membership_annual');
    }
    return $new_amount;
  }

  /**
   * @return float annual amount
   */
  public function getMembershipIncreaseAmount() {
    $value = $this->getChangeAttribute('contract_updates.ch_annual_diff');
    if (!$value) {
      // no diff recorded, try to calculate
      $before = $this->getContractDataBefore('membership_payment.membership_annual');
      $after = $this->getContractDataAfter('membership_payment.membership_annual');

      // this value's formatted, make sure there's no thousand separator in the values
      $thousand_separator = \CRM_Core_Config::singleton()->monetaryThousandSeparator;
      $before = (float) preg_replace("/[{$thousand_separator}]/", '', $before);
      $after = (float) preg_replace("/[{$thousand_separator}]/", '', $after);
      $value = $after - $before;
    }
    return (float) $value;
  }

  /**
   * @return string rendered
   */
  public function getExecutionDate($date_format = 'Y-m-d') {
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
  public function getMembershipPaymentFrequency() {
    $frequency = (int) $this->getChangeAttribute('contract_updates.ch_frequency');
    if (empty($frequency)) {
      $frequency = (int) $this->getContractAttribute('membership_payment.membership_frequency');
    }
    return \CRM_Contract_Utils::lookupOptionValue('payment_frequency', $frequency);
  }

}
