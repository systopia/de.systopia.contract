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
 * Class DisplayChangeTitle
 *
 * @note  currently, this doesn't work during the creation of the change activities,
 *   because the symfony events cause havoc there
 *
 * Allows extensions to provide a custom renderer for
 *  the subjects of change events
 *
 * @package Civi\Contract\Event
 */
class DisplayChangeTitle extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.renderchangedisplay';

  /**
   * The change activity ID
   *
   * @var integer
   */
  protected $change_activity_id;

  /**
   * The change activity type ID
   *
   * @var integer
   */
  protected $change_activity_type_id;

  /**
   * The change's display title
   *
   * @var string
   */
  protected $change_activity_display_title = null;

  /**
   * The change's hover title
   *
   * @var string
   */
  protected $change_activity_display_hover_title = null;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param integer $change_activity_type_id
   *   the change activity type ID
   *
   * @param integer $change_activity_id
   *   the change activity
   */
  public function __construct($change_activity_type_id, $change_activity_id)
  {
    $this->change_activity_id = $change_activity_id;
    $this->change_activity_type_id = $change_activity_type_id;
    $this->change_activity_display_title = null;
    $this->change_activity_display_hover_title = null;
  }

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param integer $change_activity_type_id
   *   the change activity type ID
   *
   * @param integer $change_activity_id
   *   the change activity
   *
   * @return DisplayChangeTitle;
   */
  public static function renderDisplayChangeTitleAndHoverText($change_activity_type_id, $change_activity_id)
  {
    $event = new DisplayChangeTitle($change_activity_type_id, $change_activity_id);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event;
  }

  /**
   * Get the preferred display title
   *
   * @return string
   */
  public function getDisplayTitle()
  {
    if ($this->change_activity_display_title !== null) {
      return $this->change_activity_display_title;
    } else {
      // default is: "id {change action}"
      $activity_class = CRM_Contract_Change::getClassByActivityType($this->change_activity_type_id);
      $activity_name = CRM_Contract_Change::getActionByClass($activity_class);
      return "{$this->change_activity_id} {$activity_name}";
    }
  }

  /**
   * Set the preferred display title
   *
   * @param $title
   *   the display title to be displayed for this activity
   */
  public function setDisplayTitle($title)
  {
    $this->change_activity_display_title = $title;
  }

  /**
   * Get the preferred display title
   *
   * @return string
   */
  public function getDisplayHover()
  {
    if ($this->change_activity_display_hover_title !== null) {
      return $this->change_activity_display_hover_title;
    } else {
      // default is display title
      return $this->getDisplayTitle();
    }
  }

  /**
   * Set the preferred display title hover text
   *
   * @param $title
   *   the display hover title to be displayed for this activity
   */
  public function setDisplayHoverTitle($title)
  {
    $this->change_activity_display_hover_title = $title;
  }
}
