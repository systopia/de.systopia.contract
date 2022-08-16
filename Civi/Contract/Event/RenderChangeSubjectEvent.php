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
 * Allows extensions to provide a custom renderer for
 *  the subjects of change events
 *
 * @package Civi\Contract\Event
 */
class RenderChangeSubjectEvent extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.renderchangesubject';

  /**
   * @var string the change object
   */
  protected $change_type;

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
   * @param string $change_type
   *   the change type
   *
   * @param array $contract_data_before
   *   the state of the contract before the change
   *
   * @param array $contract_data_after
   *   the state of the contract after the change
   *
   */
  public function __construct($change_type, $contract_data_before, $contract_data_after)
  {
    $this->subject = null;
    $this->change_type = $change_type;
    $this->contract_data_before = $contract_data_before;
    $this->contract_data_after = $contract_data_after;
  }


  /**
   * Issue a Symfony event to render a contract change's subject/title
   *
   * @param string $change_type
   *   change type
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
  public static function renderCustomSubject($change_type, $contract_data_before, $contract_data_after)
  {
    $custom_subject = "error";
    Civi::log()->debug("renderCustomSubject: start");
    Civi::log()->debug("change type: {$change_type}");
    $event = new RenderChangeSubjectEvent($change_type, $contract_data_before, $contract_data_after);
    Civi::log()->debug("renderCustomSubject: created");
    $event->setRenderedSubject("TOOOO");
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    Civi::log()->debug("renderCustomSubject: dispatched");
    $custom_subject = $event->getRenderedSubject();
    Civi::log()->debug("rendered: " . $custom_subject);
    if ($custom_subject) {
      // todo: remove
      Civi::log()->debug("Custom subject generated: {$custom_subject}");
    }
    return $custom_subject;
  }

  /**
   * Get the currently proposed subject
   *
   * @return string
   *    get the change type
   */
  public function getChangeType()
  {
    return $this->change_type;
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
}
