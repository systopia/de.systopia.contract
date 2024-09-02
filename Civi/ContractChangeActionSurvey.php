<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2024 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi;

use Civi\Core\Event\GenericHookEvent as Event;

/**
 * Class ContractChangeActionSurvey
 *
 * Event to collect all available ContractChange action
 */
class ContractChangeActionSurvey extends Event
{
    /** Symfony event name for the module registration */
    const EVENT_NAME = 'civi.contract.register_change_actions';

    /** @var array list of change_actions */
    protected static $change_actions = null;

    /**
     * Register a new importer module with the system
     *
     * @param string $action_name
     *   the unique module key. If it is already registered, the previous registration will be overwritten
     *
     * @param string $action_class
     *   the module's implementation class. A subclass of CRM_Contract_Change
     *
     * @param string $action_label
     *   the module's label
     *
     * @return void
     */
    public function registerChangeAction($action_name, $action_class, $action_label, $activity_type_id, $action_params = [])
    {
      // make sure the class really is a ContractAction
      if (!is_subclass_of($action_class, 'CRM_Contract_Change')) {
        throw new \Exception("Class {$action_class} is not a subclass of Civi\Contract\ContractAction");
      }

      if (isset(self::$change_actions[$action_name])) {
        \Civi::log()->debug("ContractChange action {$action_name} overwritten");
      }

      // register
      self::$change_actions[$action_name] = [
          'name' => $action_name,
          'class' => $action_class,
          'display_name' => $action_label,
          'params' => $action_params,
          'activity_type_id' => $activity_type_id,
      ];
    }

  /**
   * Get a list of all change action specs, each containing the following fields:
   *   name:         internal name
   *   class:        name of the implementation class, must be subclass of CRM_Contract_Change
   *   display_name: human-readable name
   *   params:       additional parameters
   *
   * @return array|null
   */
    public static function getChangeActions()
    {
      if (self::$change_actions === null) {
        // run the survey (once)
        self::$change_actions = [];
        $action_survey = new ContractChangeActionSurvey();
        \Civi::dispatcher()->dispatch(ContractChangeActionSurvey::EVENT_NAME, $action_survey);
      }
      return self::$change_actions;
    }
}
