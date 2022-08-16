<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Civi;
use CRM_Contract_Change as CRM_Contract_Change;

/**
 * Class RenderChangeSubjectEvent
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
   * @var CRM_Contract_Change the change object
   */
  protected $change;

  /**
   * @var array the raw contract data before
   */
  protected $contract_data_before;

  /**
   * @var array the raw contract data after
   */
  protected $contract_data_after;

  /**
   * @var string the raw contract data after
   */
  protected $subject;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param CRM_Contract_Change $change
   *   the change object
   *
   * @param array $contract_data_before
   *   the state of the contract before the change
   *
   * @param array $contract_data_after
   *   the state of the contract after the change
   *
   */
  public function __construct($change, $contract_data_before, $contract_data_after)
  {
    $this->subject = null;
    $this->change = $change;
    $this->contract_data_before = $contract_data_before;
    $this->contract_data_after = $contract_data_after;
  }


  /**
   * Issue a Symfony event to render a contract change's subject/title
   *
   * @param CRM_Contract_Change $change
   *   the change object
   *
   * @param array $contract_data_before
   *   the state of the contract before the change
   *
   * @param array $contract_data_after
   *   the state of the contract after the change
   *
   * @return string
   *   the subject line of the given change activity
   */
  public static function renderCustomSubject($change, $contract_data_before, $contract_data_after)
  {
    $event = new RenderChangeSubjectEvent($change, $contract_data_before, $contract_data_after);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event->getRenderedSubject();
  }

  /**
   * Set/override the url for the rapid create form
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
   * @return array $subject
   *    raw contract data before the change
   */
  public function getContractDataBefore()
  {
    return $this->contract_data_before;
  }

  /**
   * Get the contract data after this change
   *
   * @return array $subject
   *    raw contract data after the change
   */
  public function getContractDataAfter()
  {
    return $this->contract_data_after;
  }

  /**
   * Get a value from the data provided. It will first be taken from
   *   the *after* data, but if it doesn't contain any iformation,
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
   * Get the contract data after this change
   *
   * @return CRM_Contract_Change $change
   *    the change object that needs the subject
   */
  public function getChange()
  {
    return $this->change;
  }
}
