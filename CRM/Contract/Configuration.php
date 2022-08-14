<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Configuration options for Contract extension
 *
 * @todo create settings page
 */
class CRM_Contract_Configuration {

  /**
   * Disable monitoring relevant entities, so we don't accidentally
   *  record our own changes
   */
  public static function disableMonitoring() {
    // FIXME: Monitoring currently not implemented
  }

  /**
   * Re-enable monitoring relevant entities when
   *  we're done with our changes
   */
  public static function enableMonitoring() {
    // FIXME: Monitoring currently not implemented
  }

  /**
   * Get logged in contact ID
   *
   * @todo: make configurable
   */
  public static function getUserID() {
    $session = CRM_Core_Session::singleton();
    $contact_id = $session->getLoggedInContactID();
    if (!$contact_id) {
      // TODO: make default configurable
      $contact_id = 1;
    }
    return $contact_id;
  }

  /**
   * Allows you to suppress the automatic creation of the given activity types
   *
   * @return array list of civicrm activity types that aber being automatically created,
   *  but should be suppressed or removed
   */
  public static function suppressSystemActivityTypes() {
    $default_types = ['Membership Signup', 'Change Membership Status', 'Change Membership Type'];
    return \Civi\Contract\Event\SuppressedSystemActivityTypes::getSuppressedChangeActivityTypes($default_types);
  }

  /**
   * Allows you to adjust the list of eligible campaigns
   *
   * @return array list of civicrm activity types that aber being automatically created,
   *  but should be suppressed or removed
   */
  public static function getCampaignList() {
    // default is all campaigns (pulled on first call)
    static $all_campaigns = null;
    if ($all_campaigns === null) {
      $all_campaigns = ['' => ts('- none -')];
      $campaign_query = civicrm_api3('Campaign', 'get', [
          'sequential'   => 1,
          'is_active'    => 1,
          'option.limit' => 0,
          'return'       => 'id,title'
      ]);
      foreach ($campaign_query['values'] as $campaign) {
        $all_campaigns[$campaign['id']] = $campaign['title'];
      }
    }

    // run a symfony event to restrict that
    return \Civi\Contract\Event\EligibleContractCampaigns::getAllEligibleCampaigns($all_campaigns);
  }

  /**
   * get the list of campaigns eligible for creating
   * new contracts
   * @todo configure
   */
  public static function _getCampaignList() {
    if (self::$eligible_campaigns === NULL) {
      self::$eligible_campaigns = array(
        '' => ts('- none -'));
      $campaign_query = civicrm_api3('Campaign', 'get', array(
        'sequential'   => 1,
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,title'
        ));
      foreach ($campaign_query['values'] as $campaign) {
        self::$eligible_campaigns[$campaign['id']] = $campaign['title'];
      }
    }
    return self::$eligible_campaigns;
  }


  /**
   * Get a list of contract references that are excempt
   * from the UNIQUE contraint.
   */
  public static function getUniqueReferenceExceptions() {
    // TODO: these are GP values,
    //   create a setting to make more flexible
    return array(
      "Einzug durch TAS",
      "Vertrag durch TAS",
      "Allgemeine Daueraufträge",
      "Vertrag durch Directmail",
      "Dauerauftrag neu",
      "Vertrag durch Canvassing",
      "Einzugsermächtigung",
      "Frontline",
      "Online-Spende",
      "Greenpeace in Action",
      "Online Spende",
      "VOR",
      "Internet",
      "Onlinespende",
      "Online-Spenden",
    );
  }
}
