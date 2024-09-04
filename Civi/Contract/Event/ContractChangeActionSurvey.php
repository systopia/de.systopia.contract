<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2024 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Civi\Core\Event\GenericHookEvent as Event;
use Civi\Funding\ActivityTypeIds;
use League\Csv\Exception;

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
     * @param string $action_key
     *   the unique module shorthand key. If it is already registered, the previous registration will be overwritten
     *
     * @param string $action_name
     *   the action's name, e.g. Contract_Change
     *
     * @param string $action_class
     *   the module's implementation class name. A subclass of CRM_Contract_Change
     *
     * @param string $action_label
     *   the module's implementation class label.
     *
     * @param ?int $activity_type_id
     *   the activity_type ID that's linked to this action
     *
     * @return void
     */
    public function registerChangeAction($action_key, $action_name, $action_class, $action_label, $activity_type_id = null, $action_params = [])
    {
      // make sure the class really is a ContractAction
      if (!is_subclass_of($action_class, 'CRM_Contract_Change')) {
        throw new \Exception("Class {$action_class} is not a subclass of Civi\Contract\ContractAction");
      }

      // make sure the class obeys the naming convention
      if (!str_starts_with($action_class, 'CRM_Contract_')) {
        throw new \Exception("Name of class {$action_class} does not start with 'CRM_Contract_' (convention).");
      }

      if (isset(self::$change_actions[$action_name])) {
        \Civi::log()->debug("Existing ContractChange action {$action_name} replaced");
      }

      // look up corresponding activity type ID if not provided
      if (empty($activity_type_id)) {
        static $all_activity_types = null;
        if ($all_activity_types === null) {
          // todo: cache?
          $all_activity_types = [];
          $activity_type_query = \Civi\Api4\OptionValue::get(TRUE)
            ->addSelect('option_group_id:name', 'value', 'label', 'name')
            ->addWhere('option_group_id:name', '=', 'activity_type')
            ->addWhere('name', 'LIKE', 'Contract_%')
            ->addWhere('is_active', '=', TRUE)
            ->execute();
          foreach ($activity_type_query as $activity_type) {
            $all_activity_types[$activity_type['name']] = [
              'name' => $activity_type['name'],
              'value' => $activity_type['value'],
              'label' => $activity_type['label'],
            ];
          }
        }
        if (empty($all_activity_types[$action_name])) {
            throw new Exception("Contract change type '{$activity_type['name']}' has no corresponding activity type");
        }
        $activity_type_id = $all_activity_types[$activity_type['name']]['value'] ?? null;
      }

      // register
      self::$change_actions[$action_name] = [
          'name' => $action_name,
          'class' => $action_class,
          'display_name' => $action_label,
          'params' => $action_params,
          'activity_type_id' => $activity_type_id,
          'action' => $action_key,
      ];
    }

  /**
   * Get a list of all change action specs, each containing the following fields:
   *   name:         internal name
   *   class:        name of the implementation class, must be subclass of CRM_Contract_Change
   *   display_name: human-readable name
   *
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


  /**
   * Reset the internal cache for contract change actions
   *
   * @return void
   */
    public static function flushChangeActionCache()
    {
      self::$change_actions = null;
    }

    /**
     * Get the change type to class mapping, e.g. 'Contract_Signed' => 'CRM_Contract_Change_Sign'
     *
     * @return array
     */
    public static function getType2Class()
    {
        $type2class = [];
        foreach (self::getChangeActions() as $action_name => $action) {
            $type2class[$action_name] = $action['class'];
            if (!empty($action['activity_type_id'])) {
                $type2class[$action['activity_type_id']] = $action['class'];
            }
        }
        return $type2class;
    }

    /**
     * Get the key/verb (e.g. 'sign') to class mapping, e.g. 'sign' => 'CRM_Contract_Change_Sign'
     *
     * @return array
     */
    public static function getKey2Class()
    {
        $key2class = [];
        foreach (self::getChangeActions() as $action) {
            $key2class[$action['action']] = $action['class'];
        }
        return $key2class;
    }

    /**
     * Get the change activity type ID to class mapping, e.g. 'Contract_Signed' => 18
     *
     * @return array
     */
    public static function getAction2Class()
    {
        $action2class = [];
        $all_actions = self::getChangeActions();
        foreach ($all_actions as $action_name => $action) {
            $action2class[$action_name] = $action['class'];
        }
        return $action2class;
    }

    /**
     * Get the change activity type ID to class mapping, e.g. 'Contract_Signed' => 18
     *
     * @return array
     */
    public static function getActivityTypeId2Class()
    {
        $activityTypeId2Class = [];
        foreach (self::getChangeActions() as $action_name => $action) {
            $type2class[$action['activity_type_id']] = $action['class'];
        }
        return $activityTypeId2Class;
    }





}
