<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * Configuration options for Contract extension
 *
 * @todo create settings page
 */
class CRM_Contract_Configuration {


  /**
   * @var array*/
  protected static $eligible_campaigns = NULL;

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
   * @return array
   *   list of civicrm activity types that aber being automatically created,
   *   but should be suppressed or removed
   */
  public static function suppressSystemActivityTypes() {
    $default_types = ['Membership Signup', 'Change Membership Status', 'Change Membership Type'];
    return \Civi\Contract\Event\SuppressedSystemActivityTypes::getSuppressedChangeActivityTypes($default_types);
  }

  /**
   * Allows you to adjust the list of eligible campaigns
   *
   * @return array
   *   list of civicrm activity types that aber being automatically created,
   *   but should be suppressed or removed
   */
  public static function getCampaignList() {
    // default is all campaigns (pulled on first call)
    static $all_campaigns = NULL;
    if ($all_campaigns === NULL) {
      $all_campaigns = ['' => E::ts('- none -')];
      $campaign_query = civicrm_api3('Campaign', 'get', [
        'sequential'   => 1,
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'id,title',
      ]);
      foreach ($campaign_query['values'] as $campaign) {
        $all_campaigns[$campaign['id']] = $campaign['title'];
      }
    }

    // run a symfony event to restrict that
    return \Civi\Contract\Event\EligibleContractCampaigns::getAllEligibleCampaigns($all_campaigns);
  }

  /**
   * Get a list of contract references that are exempt from the UNIQUE contraint.
   */
  public static function getUniqueReferenceExceptions() {
    // TODO: Create a setting to make more flexible.
    return [];
  }

  /**
   * Get the list of payment instruments supported by the contract extension
   * @return array list of [payment_instrument_name => label] tuples
   *
   * @throws CRM_Core_Exception
   * @throws \Civi\API\Exception\NotImplementedException
   */
  public static function getSupportedPaymentTypes($return_ids = FALSE) {
    static $eligible_payment_option_labels = NULL;
    static $eligible_payment_option_ids = NULL;
    if ($eligible_payment_option_labels === NULL) {
      $generally_supported_payment_types = [
        // todo: setting?
        'None' => E::ts('No Payment required'),
        'RCUR' => E::ts('SEPA Direct Debit'),
        'Cash' => E::ts('Cash'),
        'EFT' => E::ts('EFT'),
      ];

      // make sure they're there and enabled
      $eligible_payment_options_query = civicrm_api4('OptionValue', 'get', [
        'select' => ['label', 'name', 'value'],
        'where' => [
          ['option_group_id:name', '=', 'payment_instrument'],
          ['name', 'IN', array_keys($generally_supported_payment_types)],
          ['is_active', '=', TRUE],
        ],
      ])->getArrayCopy();
      $eligible_payment_option_labels = [];
      $eligible_payment_option_ids = [];
      foreach ($eligible_payment_options_query as $option) {
        if ($option['name'] == 'RCUR') {
          $option['label'] = E::ts('SEPA Direct Debit');
        }
        $eligible_payment_option_labels[$option['name']] = $option['label'];
        $eligible_payment_option_ids[$option['name']] = $option['value'];
      }
    }
    if ($return_ids) {
      return $eligible_payment_option_ids;
    }
    else {
      return $eligible_payment_option_labels;
    }
  }

  public static function isCreateNewPaymentType(string $paymentTypeName): bool {
    return in_array($paymentTypeName, ['RCUR', 'Cash', 'EFT']);
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
    $payment_instrument_id_by_name = self::getSupportedPaymentTypes(TRUE);
    return $payment_instrument_id_by_name[$name] ?? NULL;
  }

  /**
   * Get the list of eligible payment options
   *
   * @return array
   *   possibly having the following values
   *   - 'select'   => a recurring_contribution_id is presented as the new contract
   *   - 'nochange' => no changes to the payment contract requested
   *   - <other>    => the new payment instrument, e.g. RCUR or Cash
   */
  public static function getPaymentOptions($allow_new_contracts = TRUE, $allow_no_change = TRUE) {
    $payment_options['select'] = E::ts('select existing');

    if ($allow_new_contracts) {
      $payment_types = CRM_Contract_Configuration::getSupportedPaymentTypes();
      foreach ($payment_types as $payment_key => $payment_type) {
        $payment_options[$payment_key] = $payment_type;
      }
    }

    if ($allow_no_change) {
      $payment_options['nochange'] = E::ts('no change');
    }
    return $payment_options;
  }

}
