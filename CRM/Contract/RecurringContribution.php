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

class CRM_Contract_RecurringContribution {

  /**
   * cached variables */
  protected $paymentInstruments = NULL;
  protected static $sepaPaymentInstruments = NULL;
  protected static $cached_results = [];

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   */
  public static function getAllForContact($cid, $thatAreNotAssignedToOtherContracts = TRUE, $contractId = NULL) {
    $object = new CRM_Contract_RecurringContribution();
    return $object->getAll($cid, $thatAreNotAssignedToOtherContracts, $contractId);
  }

  /**
   * Gets the cycle day for the given recurring contribution
   *
   * @todo caching (in this whole section)
   */
  public static function getCycleDay($recurring_contribution_id) {
    $recurring_contribution_id = (int) $recurring_contribution_id;
    if ($recurring_contribution_id) {
      try {
        $recurring_contribution = civicrm_api3('ContributionRecur', 'getsingle', [
          'id'     => $recurring_contribution_id,
          'return' => 'cycle_day',
        ]);
        if (!empty($recurring_contribution['cycle_day'])) {
          return $recurring_contribution['cycle_day'];
        }
      }
      catch (Exception $e) {
        // doesn't exist?
      }
    }
    return NULL;
  }

  /**
   * Return a detailed list of recurring contribution
   * for the given contact
   */
  public static function getCurrentContract($contact_id, $recurring_contribution_id) {
    // make sure we have the necessary information
    if (empty($contact_id) || empty($recurring_contribution_id)) {
      return [];
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id'     => $contact_id,
      'return' => 'display_name',
    ]);

    // load contribution
    $contributionRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $recurring_contribution_id]);

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // load mandate
    $sepaMandates = civicrm_api3('SepaMandate', 'get', [
      'contact_id'   => $contact_id,
      'type'         => 'RCUR',
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id'    => $recurring_contribution_id,
    ])['values'];

    $object = new CRM_Contract_RecurringContribution();
    return $object->renderRecurringContribution($contributionRecur, $contact, $sepaMandates, $sepaCreditors);
  }

  /**
   * Render all recurring contributions for that contact
   */
  public function getAll($cid, $thatAreNotAssignedToOtherContracts = TRUE, $contractId = NULL) {
    $return = [];

    // see if we have that cached (it's getting called multiple times)
    $cache_key = "{$cid}-{$thatAreNotAssignedToOtherContracts}-{$contractId}";
    if (isset(self::$cached_results[$cache_key])) {
      return self::$cached_results[$cache_key];
    }

    // load contact
    $contact = civicrm_api3('Contact', 'getsingle', [
      'id'     => $cid,
      'return' => 'display_name',
    ]);

    // load contribution
    $contributionRecurs = civicrm_api3('ContributionRecur', 'get', [
      'contact_id'             => $cid,
      'sequential'             => 0,
      'contribution_status_id' => ['IN' => $this->getValidRcurStatusIds()],
      'option.limit'           => 0,
    ])['values'];

    // load attached mandates
    if (!empty($contributionRecurs)) {
      $sepaMandates = civicrm_api3('SepaMandate', 'get', [
        'type'         => 'RCUR',
        'entity_table' => 'civicrm_contribution_recur',
        'entity_id'    => ['IN' => array_keys($contributionRecurs)],
        'option.limit' => 0,
      ])['values'];
    }
    else {
      $sepaMandates = [];
    }

    // load SEPA creditors
    $sepaCreditors = civicrm_api3('SepaCreditor', 'get')['values'];

    // render all recurring contributions
    foreach ($contributionRecurs as $cr) {
      $return[$cr['id']] = $this->renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors);
    }

    // We don't want to return recurring contributions for selection if they are
    // already assigned to OTHER contracts
    if ($thatAreNotAssignedToOtherContracts && !empty($return)) {
      // find contracts already using any of our collected recrruing contributions:
      $rcField = CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution');
      $contract_using_rcs = civicrm_api3('Membership', 'get', [
        $rcField => ['IN' => array_keys($return)],
        'return' => $rcField,
      ]);

      // remove the ones from the $return list that are being used by other contracts
      foreach ($contract_using_rcs['values'] as $contract) {
        // but leave the current one in
        if ($contract['id'] != $contractId) {
          unset($return[$contract[$rcField]]);
        }
      }
    }

    self::$cached_results[$cache_key] = $return;
    return $return;
  }

  /**
   * Render the given recurring contribution
   *
   * @param $cr array
   *   recurring contribution data
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  protected function renderRecurringContribution($cr, $contact, $sepaMandates, $sepaCreditors) {
  // phpcs:enable
    $result = [];

    $paymentInstruments = $this->getPaymentInstruments();
    $paymentInstrumentId = isset($cr['payment_instrument_id']) ? (int) $cr['payment_instrument_id'] : NULL;
    if (CRM_Contract_RecurringContribution::isSepaPaymentInstrument($paymentInstrumentId)) {
      // this is a SEPA contract
      $result['fields'] = [
        'display_name' => $contact['display_name'],
        'payment_instrument' => E::ts('SEPA'),
        'frequency' => $this->writeFrequency($cr),
        'amount' => CRM_Contract_SepaLogic::formatMoney($cr['amount']),
        'annual_amount' => CRM_Contract_SepaLogic::formatMoney($this->calcAnnualAmount($cr)),
      ];
      $mandate = $this->getSepaByRecurringContributionId((int) $cr['id'], $sepaMandates);
      if (empty($mandate)) {
        // phpcs:disable Generic.Files.LineLength.TooLong
        Civi::log()->debug(
          "Data inconsistency: recurring contribution [{$cr['id']}] has a SEPA payment instrument, but no recurring contribution"
        );
        // phpcs:enable
      }
      $mandateReference = '';
      if (is_array($mandate) && isset($mandate['reference'])) {
        $mandateReference = $mandate['reference'];
      }

      $sepa_creditor_id = $mandate['creditor_id'] ?? NULL;
      $result['fields']['iban'] = $mandate['iban'] ?? '';
      $result['fields']['bic'] = $mandate['bic'] ?? '';
      $result['fields']['org_iban'] = $sepa_creditor_id ? ($sepaCreditors[$sepa_creditor_id]['iban']) : '';
      $result['fields']['creditor_name'] = $sepa_creditor_id ? ($sepaCreditors[$sepa_creditor_id]['name']) : '';
      $result['fields']['next_debit'] = substr($cr['next_sched_contribution_date'] ?? '', 0, 10);
      $result['label'] =
        "SEPA, {$result['fields']['amount']} {$result['fields']['frequency']} ({$mandateReference})";
      // todo: use template? consolidate with payment preview
      $result['text_summary'] =
        E::ts('Debitor name') . ": {$result['fields']['display_name']}<br /> " .
        E::ts('Debitor account') . ": {$result['fields']['iban']}<br /> " .
        E::ts('Creditor name') . ": {$result['fields']['creditor_name']}<br /> " .
        E::ts('Creditor account') . ": {$result['fields']['org_iban']}<br /> " .
        E::ts('Payment method') . ": {$result['fields']['payment_instrument']}<br /> " .
        E::ts('Frequency') . ": {$result['fields']['frequency']}<br /> " .
        E::ts('Annual amount') . ": {$result['fields']['annual_amount']}&nbsp;{$cr['currency']}<br /> " .
        E::ts('Installment amount') . ": {$result['fields']['amount']}&nbsp;{$cr['currency']}<br /> " .
        E::ts('Next debit') . ": {$result['fields']['next_debit']}";

    }
    else {
      $result['fields'] = [
        'display_name' => $contact['display_name'],
        'payment_instrument' => $paymentInstruments[$cr['payment_instrument_id'] ?? NULL] ?? NULL,
        'frequency' => $this->writeFrequency($cr),
        'amount' => CRM_Contract_SepaLogic::formatMoney($cr['amount']),
        'annual_amount' => CRM_Contract_SepaLogic::formatMoney($this->calcAnnualAmount($cr)),
        'next_debit' => '?',
      ];

      if ($result['fields']['payment_instrument'] == 'No payment required') {
        $result['text_summary'] = E::ts('No payment required');
        $result['label'] = E::ts('No payment required');
      }
      else {
        // this is a non-SEPA recurring contribution
        $result['text_summary'] =
          E::ts('Paid by') . ": {$result['fields']['display_name']}<br />" .
          E::ts('Payment method') . ": {$result['fields']['payment_instrument']}<br />" .
          E::ts('Frequency') . ": {$result['fields']['frequency']}<br />" .
          E::ts('Annual amount') . ": {$result['fields']['annual_amount']}&nbsp;{$cr['currency']}<br />" .
          E::ts('Installment amount') . ": {$result['fields']['amount']}&nbsp;{$cr['currency']}<br />";
        $result['label'] =
          "{$result['fields']['payment_instrument']}, {$result['fields']['amount']} {$result['fields']['frequency']}";

      }
    }

    return $result;
  }

  /**
   * Get the status IDs for eligible recurring contributions
   */
  protected function getValidRcurStatusIds() {
    $pending_id = CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'Pending'
    );
    $current_id = CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'In Progress'
    );
    return [$pending_id, $current_id];
  }

  /**
   * ??
   * @author Michael
   */
  private function writeFrequency($cr) {
    if ($cr['frequency_interval'] == 1) {
      $frequency = "Every {$cr['frequency_unit']}";
    }
    else {
      $frequency = "Every {$cr['frequency_interval']} {$cr['frequency_unit']}s";
    }

    // FIXME: use SepaLogic::getPaymentFrequencies
    $shortHands = [
      'Every 12 months' => E::ts('annually'),
      'Every year'      => E::ts('annually'),
      'Every month'     => E::ts('monthly'),
    ];
    if (array_key_exists($frequency, $shortHands)) {
      return $shortHands[$frequency];
    }
    return $frequency;
  }

  /**
   * ??
   * @author Michael
   */
  private function calcAnnualAmount($cr) {
    if ($cr['frequency_unit'] == 'month') {
      $multiplier = 12;
    }
    elseif ($cr['frequency_unit'] == 'year') {
      $multiplier = 1;
    }
    return $cr['amount'] * $multiplier / $cr['frequency_interval'];
  }

  /**
   * ??
   * @author Michael
   */
  public function writePaymentContractLabel($contributionRecur) {
    $paymentInstruments = $this->getPaymentInstruments();
    if (in_array($contributionRecur['payment_instrument_id'],
      CRM_Contract_RecurringContribution::getSepaPaymentInstruments()
    )) {
      $sepaMandate = civicrm_api3('SepaMandate', 'getsingle', [
        'entity_table' => 'civicrm_contribution_recur',
        'entity_id' => $contributionRecur['id'],
      ]);

      $plural = $contributionRecur['frequency_interval'] > 1 ? 's' : '';
      // phpcs:disable Generic.Files.LineLength.TooLong
      return "SEPA: {$sepaMandate['reference']} ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']}{$plural})";
      // phpcs:enable
    }
    else {
      // phpcs:disable Generic.Files.LineLength.TooLong
      return "{$paymentInstruments[$contributionRecur['payment_instrument_id']]}: ({$contributionRecur['amount']} every {$contributionRecur['frequency_interval']} {$contributionRecur['frequency_unit']})";
      // phpcs:enable
    }
  }

  /**
   * get all payment instruments
   */
  protected function getPaymentInstruments() {
    if (!isset($this->paymentInstruments)) {
      // load payment instruments
      $paymentInstrumentOptions = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'payment_instrument',
        'option.limit' => 0,
      ]
        )['values'];
      $this->paymentInstruments = [];
      foreach ($paymentInstrumentOptions as $paymentInstrumentOption) {
        $this->paymentInstruments[$paymentInstrumentOption['value']] = $paymentInstrumentOption['label'];
      }
    }
    return $this->paymentInstruments;
  }

  /**
   * Get all CiviSEPA payment instruments(?)
   *
   * @return mixed
   * @author Michael
   */
  public static function getSepaPaymentInstruments() {
    if (!isset(static::$sepaPaymentInstruments)) {
      static::$sepaPaymentInstruments = [];
      $result = civicrm_api3(
        'OptionValue',
        'get',
        [
          'option_group_id' => 'payment_instrument',
          'name' => ['IN' => ['RCUR', 'OOFF', 'FRST']],
        ]
      );
      foreach ($result['values'] as $paymentInstrument) {
        static::$sepaPaymentInstruments[] = $paymentInstrument['value'];
      }
    }

    return static::$sepaPaymentInstruments;
  }

  /**
   * Check if the given payment instrument is a SEPA one
   *
   * @param ?int $payment_instrument_id
   *    a payment instrument ID to test
   *
   * @return bool
   *   is it SEPA?
   */
  public static function isSepaPaymentInstrument(?int $payment_instrument_id) {
    return in_array($payment_instrument_id, CRM_Contract_RecurringContribution::getSepaPaymentInstruments());
  }

  /**
   * Get the CiviSEPA mandate id connected to the given recurring contribution,
   * from the given list.
   *
   * @param int $rcur_id
   *   recurring contribution ID
   *
   * @param array $sepa_mandates
   *   list of eligible sepa mandates that have already been loaded
   *
   * @return ?array
   *   SEPA mandate data
   *
   *
   * @author Michael
   *
   */
  private function getSepaByRecurringContributionId(int $rcur_id, array $sepa_mandates) {
    foreach ($sepa_mandates as $sepa_mandate) {
      if ($sepa_mandate['entity_id'] == $rcur_id && $sepa_mandate['entity_table'] == 'civicrm_contribution_recur') {
        return $sepa_mandate;
      }
    }
    return NULL;
  }

  public static function createRecurringContribution(
    int $contactId,
    string $amount,
    string $startDate,
    string $accountHolder,
    string $paymentOption,
    int $cycleDay,
    int $frequencyInterval,
    ?int $campaignId
  ): int {
    $payment_contract_params = [
      'contact_id' => $contactId,
      'amount' => $amount,
      // TODO: Why use currency from SEPA creditor?
      'currency' => CRM_Contract_SepaLogic::getCreditor()->currency,
      'start_date' => CRM_Utils_Date::processDate($startDate, NULL, NULL, 'Y-m-d H:i:s'),
      // NOW
      'create_date' => date('YmdHis'),
      'date' => CRM_Utils_Date::processDate($startDate, NULL, NULL, 'Y-m-d H:i:s'),
      // NOW
      'validation_date' => date('YmdHis'),
      'account_holder' => $accountHolder,
      'campaign_id' => $campaignId ?? '',
      'payment_instrument_id' => CRM_Contract_Configuration::getPaymentInstrumentIdByName($paymentOption),
      // Membership Dues
      'financial_type_id' => 2,
      'frequency_unit' => 'month',
      'cycle_day' => $cycleDay,
      'frequency_interval' => $frequencyInterval,
      'checkPermissions' => TRUE,
    ];
    CRM_Contract_CustomData::resolveCustomFields($payment_contract_params);
    $new_recurring_contribution = civicrm_api3('ContributionRecur', 'create', $payment_contract_params);
    if ((bool) $new_recurring_contribution['is_error']) {
      throw new RuntimeException('Error creating recurring contribution');
    }
    return $new_recurring_contribution['id'];
  }

}
