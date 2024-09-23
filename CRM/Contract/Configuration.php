<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

/**
 * Configuration options for Contract extension
 *
 * @todo create settings page
 */
class CRM_Contract_Configuration {


  /** @var array $eligible_campaigns */
  protected static $eligible_campaigns = null;

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
   * Get a list of contract references that are excempt
   * from the UNIQUE contraint.
   */
  public static function getUniqueReferenceExceptions() {
    // TODO: these are GP values,
    //   create a setting to make more flexible
    return [
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
    ];
  }

  /**
   * Get the list of payment instruments supported by the contract extension
   * @return array list of [payment_instrument_name => label] tuples
   *
   * @throws CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   */
  public static function getSupportedPaymentTypes($return_ids = false)
  {
    static $eligible_payment_option_labels = null;
    static $eligible_payment_option_ids = null;
    if ($eligible_payment_option_labels === null) {
      $generally_supported_payment_types = [
        // todo: setting?
        'RCUR' => E::ts("SEPA Lastschrift"),
        'Cash' => E::ts("Barzahlung"),
        'EFT' => E::ts("Überweisung"),
      ];

      // make sure they're there and enabled
      $eligible_payment_options_query = civicrm_api4('OptionValue', 'get', [
        'select' => ['label', 'name', 'value'],
        'where' => [
          ['option_group_id:name', '=', 'payment_instrument'],
          ['name', 'IN', array_keys($generally_supported_payment_types)],
          ['is_active', '=', true]
        ],
      ])->getArrayCopy();
      $eligible_payment_option_labels = [];
        $eligible_payment_option_ids = [];
      foreach ($eligible_payment_options_query as $option) {
        if ($option['name'] == 'RCUR') {
          $option['label'] = E::ts("SEPA Lastschrift");
        }
        $eligible_payment_option_labels[$option['name']] = $option['label'];
          $eligible_payment_option_ids[$option['name']] = $option['value'];
      }
    }
    if ($return_ids) {
        return $eligible_payment_option_ids;
    } else {
        return $eligible_payment_option_labels;
    }
  }

    /**
     * Retrieve the payment instrument ID for the given payment mode
     *
     * @param string $name
     *   the name as returned by getSupportedPaymentTypes()
     *
     * @return ?int
     */
  public static function getPaymentInstrumentIdByName($name) {
    $payment_instrument_id_by_name = self::getSupportedPaymentTypes(true);
    return $payment_instrument_id_by_name[$name] ?? null;
  }
}
