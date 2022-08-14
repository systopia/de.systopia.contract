<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Symfony\Component\EventDispatcher\Event;
use \CRM_Contract_Change as CRM_Contract_Change;

/**
 * Class RenderChangeSubjectEvent
 *
 * Allows extensions to provide a custom renderer for
 *  the subjects of change events
 *
 * @package Civi\RemoteEvent\Event
 */
class RenderChangeSubjectEvent extends ConfigurationEvent
{
  public static string $event_name = 'de.contract.renderchangesubject';

  /**
   * @var CRM_Contract_Change the change object
   */
  protected CRM_Contract_Change $change;

  /**
   * @var array the raw contract data before
   */
  protected array $contract_data_before;

  /**
   * @var array the raw contract data after
   */
  protected array $contract_data_after;

  /**
   * @var string the raw contract data after
   */
  protected string $subject;

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
    \Civi::dispatcher()->dispatch(self::$event_name, $event);
    return $event->getSubject();
  }

  /**
   * Set/override the url for the rapid create form
   *
   * @param string $subject
   *    the proposed subject for the change
   */
  public function setSubject($subject)
  {
    $this->subject = $subject;
  }

  /**
   * Get the currently proposed subject
   *
   * return string $subject
   *    the proposed subject for the change
   */
  public function getSubject()
  {
    return $this->subject;
  }

  /**
   * Get the contract data before this change
   *
   * @return array $subject
   *    the proposed subject for the change
   */
  public function getContractDataBefore()
  {
    return $this->contract_data_before;
  }

  /**
   * Get the contract data after this change
   *
   * @return array $subject
   *    the proposed subject for the change
   */
  public function getContractDataAfter()
  {
    return $this->contract_data_after;
  }
}
