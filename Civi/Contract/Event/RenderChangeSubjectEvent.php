<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Civi;

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
class RenderChangeSubjectEvent extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.renderchangesubject';

  /**
   * @var integer the id of the change record (activity)
   */
  protected $change_id;

  /**
   * @var array the raw contract data before
   */
  protected $contract_data_before;

  /**
   * @var array the raw contract data after
   */
  protected $contract_data_after;

  /**
   * @var array the raw change/activity data
   */
  protected $change_activity_data;

  /**
   * @var string the raw contract data after
   */
  protected $subject;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param integer $change_id
   *   the id of the change record (activity)
   *
   * @param array $contract_data_before
   *   the state of the contract before the change
   *
   * @param array $contract_data_after
   *   the state of the contract after the change
   *
   */
  public function __construct($change_id, $contract_data_before, $contract_data_after)
  {
    $this->subject = null;
    $this->change_id = $change_id;
    $this->contract_data_before = $contract_data_before;
    $this->contract_data_after = $contract_data_after;
  }


  /**
   * Issue a Symfony event to render a contract change's subject/title
   *
   * @param integer|null $change_id
   *   the id of the change record (activity)
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
  public static function renderCustomChangeSubject($change_id, $contract_data_after, $contract_data_before)
  {
    // create and run event
    $event = new RenderChangeSubjectEvent($change_id, $contract_data_before, $contract_data_after);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);

    $custom_subject = $event->getRenderedSubject();
    if ($custom_subject) {
      // todo: remove
      Civi::log()->debug("Custom subject generated: {$custom_subject}");
    }
    return $custom_subject;
  }

  /**
   * Set/override the subject for the change activity
   *
   * @param string $subject
   *    the proposed subject for the change
   */
  public function setRenderedSubject($subject)
  {
    $this->subject = $subject;
  }

  /**
   * Get the currently proposed subject
   *
   * @return string $subject
   *    the proposed subject for the change
   */
  public function getRenderedSubject()
  {
    return $this->subject;
  }

  /**
   * Get the contract data before this change
   *
   * @return array|null $subject
   *    raw contract data before the change
   */
  public function getContractDataBefore()
  {
    return $this->contract_data_before;
  }

  /**
   * Get the contract data after this change
   *
   * @return array|null $subject
   *    raw contract data after the change
   */
  public function getContractDataAfter()
  {
    return $this->contract_data_after;
  }

  /**
   * Get the change/activity ID
   *
   * @return integer $subject
   *    raw contract data after the change
   */
  public function getChangeID()
  {
    return $this->change_id;
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
  public function getAttribute($attribute_name)
  {
    return $this->contract_data_after[$attribute_name] ?? $this->contract_data_before[$attribute_name] ?? null;
  }

  /**
   * Get the activity data
   *
   * @return array activity data
   */
  public function getChangeActivityData()
  {
    if (empty($this->change_activity_data) && !empty($this->getChangeID())) {
      // todo: isn't that cached somewhere?
      $this->change_activity_data = civicrm_api3('Activity','getsingle', ['id' => $this->getChangeID()]);
      \CRM_Contract_CustomData::labelCustomFields($this->change_activity_data);
    }
    return $this->change_activity_data;
  }

  /**
   * Get the action name of the change
   *
   * @return string
   */
  public function getActivityAction()
  {
    $data = $this->getChangeActivityData();
    $class = \CRM_Contract_Change::getClassByActivityType($data['activity_type_id']);
    return \CRM_Contract_Change::getActionByClass($class);
  }

}
