<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Utils {

  private static $_singleton;
  private static $coreMembershipHistoryActivityIds;
  public static $customFieldCache;

  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Contract_Utils();
    }
    return self::$_singleton;
  }

  /**
   * Get the name (not the label) of the given membership status ID
   *
   * @param $status_id integer status ID
   * @return string status name
   */
  public static function getMembershipStatusName($status_id) {
    static $status_names = NULL;
    if ($status_names === NULL) {
      $status_names = [];
      $status_query = civicrm_api3('MembershipStatus', 'get', [
        'return'       => 'id,name',
        'option.limit' => 0,
      ]);
      foreach ($status_query['values'] as $status) {
        $status_names[$status['id']] = $status['name'];
      }
    }
    return $status_names[$status_id] ?? NULL;
  }

  public static function getCustomFieldId($customField) {

    self::warmCustomFieldCache();

    // Look up if not in cache
    if (!isset(self::$customFieldCache[$customField])) {
      $parts = explode('.', $customField);
      try {
        /** @var string $customFieldId */
        $customFieldId = civicrm_api3(
          'CustomField',
          'getvalue',
          [
            'return' => 'id',
            'custom_group_id' => $parts[0],
            'name' => $parts[1],
          ]
        );
        self::$customFieldCache[$customField] = 'custom_' . $customFieldId;
      }
      catch (Exception $e) {
        throw new \RuntimeException(
          "Could not find custom field '{$parts[1]}' in custom field set '{$parts[0]}'",
          $e->getCode(),
          $e
        );
      }
    }

    // Return result or return an error if it does not exist.
    if (isset(self::$customFieldCache[$customField])) {
      return self::$customFieldCache[$customField];
    }
    else {
      throw new \RuntimeException('Could not find custom field id for ' . $customField);
    }
  }

  public static function getCustomFieldName($customFieldId) {

    self::warmCustomFieldCache();
    $name = array_search($customFieldId, self::$customFieldCache);
    if (!$name) {
      $customField = civicrm_api3(
        'CustomField',
        'getsingle',
        ['id' => substr($customFieldId, 7)]
      );
      $customGroup = civicrm_api3(
        'CustomGroup',
        'getsingle',
        ['id' => $customField['custom_group_id']]
      );
      self::$customFieldCache["{$customGroup['name']}.{$customField['name']}"] = $customFieldId;
    }
    // Return result or return an error if it does not exist.
    if ($name = array_search($customFieldId, self::$customFieldCache)) {
      return $name;
    }
    else {
      throw new \RuntimeException('Could not find custom field for id' . $customFieldId);
    }
  }

  public static function warmCustomFieldCache() {
    if (!self::$customFieldCache) {
      $customGroupNames = [
        'membership_general',
        'membership_payment',
        'membership_cancellation',
        'contract_cancellation',
        'contract_updates',
      ];
      $custom_group_ids = [];
      $customGroups = civicrm_api3(
        'CustomGroup',
        'get',
        [
          'name' => ['IN' => $customGroupNames],
          'return' => 'name',
          'options' => ['limit' => 0],
        ]
      )['values'];
      foreach ($customGroups as $customGroup) {
        $custom_group_ids[] = $customGroup['id'];
      }
      $customFields = civicrm_api3(
        'CustomField',
        'get',
        [
          'custom_group_id' => ['IN' => $custom_group_ids],
          'options' => ['limit' => 0],
        ]
      );
      foreach ($customFields['values'] as $c) {
        self::$customFieldCache["{$customGroups[$c['custom_group_id']]['name']}.{$c['name']}"] = "custom_{$c['id']}";
      }
    }
  }

  public static function getCoreMembershipHistoryActivityIds() {
    if (!self::$coreMembershipHistoryActivityIds) {
      $result = civicrm_api3(
        'OptionValue',
        'get',
        [
          'option_group_id' => 'activity_type',
          'name' => [
            'IN' => [
              'Membership Signup',
              'Membership Renewal',
              'Change Membership Status',
              'Change Membership Type',
              'Membership Renewal Reminder',
            ],
          ],
        ]
      );
      foreach ($result['values'] as $v) {
        self::$coreMembershipHistoryActivityIds[] = $v['value'];
      }
    }
    return self::$coreMembershipHistoryActivityIds;
  }

  /**
   * Get a (cached) list of all membership types
   *
   * @return array membership type data as delivered by the API
   */
  public static function getMembershipTypes() {
    static $membership_types = NULL;
    if ($membership_types === NULL) {
      $membership_types = \civicrm_api3('MembershipType', 'get', ['option.limit' => 0])['values'];
    }
    return $membership_types;
  }

  /**
   * Get the (cached) membership type data, or a particular attribute
   *
   * @return array|string membership type data as delivered by the API
   */
  public static function getMembershipType($membership_type_id, $attribute = NULL) {
    $types = self::getMembershipTypes();
    if (isset($types[$membership_type_id])) {
      if ($attribute) {
        return $types[$membership_type_id][$attribute] ?? NULL;
      }
      else {
        return $types[$membership_type_id] ?? NULL;
      }
    }
  }

  /**
   * Download contract file
   * @param $file
   *
   * @return bool
   */
  public static function downloadContractFile($file) {
    if (!CRM_Contract_Utils::contractFileExists($file)) {
      return FALSE;
    }
    $fullPath = CRM_Contract_Utils::contractFilePath($file);

    ignore_user_abort(TRUE);
    // disable the time limit for this script
    set_time_limit(0);

    if ($fd = fopen($fullPath, 'r')) {
      $fsize      = filesize($fullPath);
      $path_parts = pathinfo($fullPath);
      $ext        = strtolower($path_parts['extension']);
      header('Content-type: application/octet-stream');
      header('Content-Disposition: filename="' . $path_parts['basename'] . '"');
      header("Content-length: $fsize");
      //use this to open files directly
      header('Cache-control: private');
      while (!feof($fd)) {
        $buffer = fread($fd, 2048);
        echo $buffer;
      }
    }
    fclose($fd);
    exit;
  }

  /**
   * Check if contract file exists, return false if not
   * @param $logFile
   * @return boolean
   */
  public static function contractFileExists($file) {
    $fullPath = CRM_Contract_Utils::contractFilePath($file);
    if ($fullPath) {
      if (file_exists($fullPath)) {
        return $fullPath;
      }
    }
    return FALSE;
  }

  /**
   * Simple function to get real file name from contract number
   * @param $file
   *
   * @return string
   */
  public static function contractFileName($file) {
    return $file . '.tif';
  }

  /**
   * This is hardcoded so contract files must be stored in customFileUploadDir/contracts/
   * Extension hardcoded to .tif
   * FIXME: This could be improved to use a setting to configure this.
   *
   * @param $file
   *
   * @return bool|string
   */
  public static function contractFilePath($file) {
    // We need a valid filename
    if (empty($file)) {
      return FALSE;
    }

    // Use the custom file upload dir as it's protected by a Deny from All in htaccess
    $config = CRM_Core_Config::singleton();
    if (!empty($config->customFileUploadDir)) {
      $fullPath = $config->customFileUploadDir . '/contracts/';
      if (!is_dir($fullPath)) {
        Civi::log()->debug(
          'Warning: Contract file path does not exist.  It should be at: ' . $fullPath
        );
      }
      $fullPathWithFilename = $fullPath . self::contractFileName($file);
      return $fullPathWithFilename;
    }
    else {
      Civi::log()->debug(
        'Warning: Contract file path undefined! Did you set customFileUploadDir?'
      );
      return FALSE;
    }
  }

  /**
   * If configured this way, this call will delete the defined
   *  list of system-generated activities
   *
   * @param $contract_id int the contract number
   */
  public static function deleteSystemActivities($contract_id) {
    if (empty($contract_id)) {
      return;
    }

    $activity_types_to_delete = CRM_Contract_Configuration::suppressSystemActivityTypes();
    if (!empty($activity_types_to_delete)) {
      // find them
      $activity_search = civicrm_api3('Activity', 'get', [
        'source_record_id'   => $contract_id,
        'activity_type_id'   => ['IN' => $activity_types_to_delete],
        'activity_date_time' => ['>=' => date('Ymd') . '000000'],
        'return'             => 'id',
      ]);

      // delete them
      foreach ($activity_search['values'] as $activity) {
        civicrm_api3('Activity', 'delete', ['id' => $activity['id']]);
      }
    }
  }

  public static function formatExceptionForActivityDetails(Exception $e) {
    return "Error was: {$e->getMessage()}<br><pre>{$e->getTraceAsString()}</pre>";
  }

  public static function formatExceptionForApi(Exception $e) {
    return $e->getMessage() . "\r\n" . $e->getTraceAsString();
  }

  /**
   * Strip all custom_* elements from $data unless they're contract activity fields
   *
   * This serves as a workaround for an APIv3 issue where a call to Activity.get
   * with the "return" parameter set to any custom field will return all other
   * custom fields that have a default value set, even if the custom field is
   * not enabled for the relevant (contract) activity type
   *
   * @todo remove this code once APIv4 is used
   *
   * @param array $data
   */
  public static function stripNonContractActivityCustomFields(array &$data) {
    // whitelist of contract activity custom fields
    $allowedFields = array_map(
      function($field) {
        return $field['id'];
      },
      CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation', 'contract_updates'])
    );
    foreach ($data as $field => $value) {
      if (substr($field, 0, 7) === 'custom_') {
        $customFieldId = substr($field, 7);
        if (!in_array($customFieldId, $allowedFields)) {
          // field starts with custom_ and ID is not on whitelist => remove
          unset($data[$field]);
        }
      }
    }
  }

  /**
   * Cached query for API lookups
   *
   * @param $entity    string entity
   * @param $query     array query options
   * @param $attribute string attribute having the desired value
   * @return mixed value
   */
  public static function lookupValue($entity, $attribute, $query) {
    static $lookup_cache = [];

    // create a key
    $query['return'] = $attribute;
    $cache_key = "$entity" . serialize($query);
    if (!isset($lookup_cache[$cache_key])) {
      try {
        $value = civicrm_api3($entity, 'getvalue', $query);
      }
      catch (Exception $ex) {
        Civi::log()->debug(
          "Error looking up {$entity} value, attribute '{$attribute}' with query " . json_encode(
            $query
          )
        );
        $value = 'ERROR';
      }
      $lookup_cache[$cache_key] = $value;
    }
    return $lookup_cache[$cache_key];
  }

  /**
   * Cached query for API lookups
   *
   * @param string $option_group_name
   *   OptionGroup (internal) name
   *
   * @param string $value
   *   the OptionValue value field content
   *
   * @param string $attribute
   *     attribute having the desired value
   *
   * @return mixed value
   */
  public static function lookupOptionValue($option_group_name, $value, $attribute = 'label') {
    return self::lookupValue('OptionValue', $attribute, [
      'option_group_id' => $option_group_name,
      'value'           => $value,
    ]);
  }

  public static function getActiveContractCount(int $contactId): int {
    /** @phpstan-var int $count */
    $count = \civicrm_api3('Contract', 'getcount', [
      'contact_id' => $contactId,
      'active_only' => 1,
    ]);
    return $count;
  }

}
