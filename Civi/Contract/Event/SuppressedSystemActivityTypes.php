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
 * Class SuppressedSystemActivityTypes
 *
 * Allows extensions adjust the list of system-generated change activities that should be suppressed
 *
 * @package Civi\RemoteEvent\Event
 */
class SuppressedSystemActivityTypes extends ConfigurationEvent
{
  public static string $event_name = 'de.contract.suppress_system_activity_types';

  /**
   * @var array
   *  the list of activity types (names or ids) to be suppressed
   */
  protected array $activity_types;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param array $suppressed_activity_types
   *   list of suggested activity types to be suppressed
   */
  public function __construct($suppressed_activity_types)
  {
    $this->activity_types = $suppressed_activity_types;
  }

  /**
   * Allows you to render the subject of a new change record (activity)
   *
   * @param $suppressed_activity_types array
   *   list of suggested activity types to be suppressed
   *
   * @return array
   *   list of activity types to be suppressed
   */
  public static function getSuppressedChangeActivityTypes($suppressed_activity_types = [])
  {
    $event = new SuppressedSystemActivityTypes($suppressed_activity_types);
    \Civi::dispatcher()->dispatch(self::$event_name, $event);
    return $event->activity_types;
  }

  /**
   * Set/override the url for the rapid create form
   *
   * @param array $activity_types
   *    the proposed subject for the change
   */
  public function setSuppressedActivityTypes($activity_types)
  {
    $this->activity_types = $activity_types;
  }

  /**
   * Get the currently proposed activity_types
   *
   * return array $activity_types
   *    the proposed activity_types to be suppressed
   */
  public function getSuppressedActivityTypes()
  {
    return $this->activity_types;
  }

  /**
   * Add a suppressed activity type
   *
   * @param string $activity_type_name
   *   activity type name
   */
  public function addSuppressedActivityType($activity_type_name)
  {
    if (!in_array($activity_type_name, $this->activity_types)) {
      $this->activity_types[] = $activity_type_name;
    }
  }

  /**
   * Remove an activity type from the suppression list
   *
   * @param string $activity_type_name
   *   activity type name
   */
  public function removeSuppressedActivityType($activity_type_name)
  {
    $new_list = [];
    foreach ($this->activity_types as $activity_type) {
      if ($activity_type != $activity_type_name) {
        $new_list[] = $activity_type;
      }
    }
    $this->activity_types = $new_list;
  }
}
