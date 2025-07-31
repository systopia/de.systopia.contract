<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Action\AbstractAction;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\ActionProvider\Parameter\Specification;
use Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Contract_ExtensionUtil as E;
use CRM_Contract_BankingLogic as B;

class UpdateContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([

      new Specification(
        'process_scheduled_modifications',
        'Integer',
        E::ts('directly process scheduled changes'),
        FALSE,
        0,
        NULL,
        $this->yesNoOptions()
      ),
      new Specification(
        'config_defer_payment_start',
        'Boolean',
        E::ts('Defer Payment Start'),
        FALSE,
        FALSE
      ),

    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
      // required fields
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), TRUE),
      new Specification('contract_id', 'Integer', E::ts('Contract ID'), TRUE),
      new Specification('membership_type_id', 'Integer', E::ts('Membership Type ID'), FALSE),
      new Specification('defer_payment_start', 'Boolean', E::ts('Defer Payment Start'), FALSE),
      new Specification('iban', 'String', E::ts('IBAN'), FALSE),
      new Specification('bic', 'String', E::ts('BIC'), FALSE),
      new Specification('contract_updates_ch_annual', 'Money', E::ts('Annual Amount'), FALSE),
      new Specification('contract_updates_reference', 'String', E::ts('Mandate Reference'), FALSE),
      // recurring information
      new Specification(
        'contract_updates_ch_frequency',
        'Integer',
        E::ts('Frequency'),
        FALSE,
        12,
        NULL,
        $this->getFrequencies()
      ),
      new Specification(
        'contract_updates_ch_cycle_day',
        'Integer',
        E::ts('Collection Day'),
        FALSE,
        1,
        NULL,
        $this->getCollectionDays()
      ),
      new Specification('date', 'Date', E::ts('Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('account_holder', 'String', E::ts('Members Bank Account'), FALSE),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id', 'Integer', E::ts('Mandate ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('mandate_reference', 'String', E::ts('Mandate Reference'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('error', 'String', E::ts('Error Message (if creation failed)'), FALSE, NULL, NULL, NULL, FALSE),
    ]);
  }

  /**
   * Run the action
   *
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $output
   *      The parameters this action can send back
   * @return void
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
  // phpcs-enable
    $contract_data = ['action' => 'update'];
    // add basic fields to contract_data
    foreach ([
      'contact_id',
      'iban',
      'bic',
      'date',
      'membership_type_id',
      'contract_updates_ch_annual',
      'contract_updates_reference',
      'contract_updates_ch_frequency',
      'contract_updates_ch_cycle_day',
    ] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $contract_data[$parameter_name] = $value;
      }
    }
    // add override fields to contract_data
    foreach (['membership_type_id', 'contact_id', 'contract_id'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $contract_data[$parameter_name] = $value;
    }
    // add account holder
    $account_holder = $parameters->getParameter('account_holder');
    if (!empty($account_holder)) {
      $contract_data['membership_payment_from_name'] = $account_holder;
    }
    // add defer_payment_start
    $defer_payment_start = $parameters->getParameter('defer_payment_start');
    if (!empty($defer_payment_start)) {
      $contract_data['membership_payment_defer_payment_start'] = $defer_payment_start;
    }
    $config_defer_payment_start = $this->configuration->getParameter('config_defer_payment_start');
    if (!empty($config_defer_payment_start)) {
      $contract_data['membership_payment_defer_payment_start'] = $config_defer_payment_start;
    }

    // explicitly add cycle_day if not given, just to make sure it doesn't change by default
    if (empty($contract_data['contract_updates_ch_cycle_day']) && !empty($contract_data['contract_id'])) {
      try {
        $cycle_day_field = \CRM_Contract_CustomData::getCustomFieldKey('membership_payment', 'cycle_day');
        $current_cycle_day = \civicrm_api3('Contract', 'getvalue', [
          'id' => $contract_data['contract_id'],
          'return' => $cycle_day_field,
        ]);
        $contract_data['contract_updates_ch_cycle_day'] = $current_cycle_day;
      }
      catch (\CiviCRM_API3_Exception $ex) {
        \Civi::log()->debug("Couldn't extract current cycle day from contract [{$contract_data['contract_id']}].");
      }
    }

    try {
      // update bank account if new iban is set
      if ((!empty($parameters->getParameter('iban'))) or (!empty($parameters->getParameter('bic')))) {
        $contract_data['membership_payment_from_ba'] = B::getOrCreateBankAccount(
          (int) $parameters->getParameter('contact_id'),
          $parameters->getParameter('iban'),
          $parameters->getParameter('bic')
        );
      }

      // update contract
      $contract = \civicrm_api3('Contract', 'modify', $contract_data);
      $output->setParameter('contract_id', $contract['id']);

      if ($this->configuration->getParameter('process_scheduled_modifications') == 1) {
        $process_scheduled_modifications = \civicrm_api3(
          'Contract',
          'process_scheduled_modifications',
          ['contract_id' => $contract['contract_id']]
        );
      }
    }
    catch (\Exception $ex) {
      $output->setParameter('contract_id', '');
      $output->setParameter('error', $ex->getMessage());
    }
  }

  /**
   * Get a list of all modify actions
   */
  protected function getModifyActions() {
    $modify_actions = [
      'sign' => 'sign',
      'cancel' => 'cancel',
      'update' => 'update',
      'resume' => 'resume',
      'revive' => 'revive',
      'pause' => 'pause',
    ];
    return $modify_actions;
  }

  /**
   * Get a list of all membership types
   */
  protected function getMembershipTypes() {
    $creditor_list = [];
    $creditor_query = \civicrm_api3('MembershipType', 'get', ['option.limit' => 0]);
    foreach ($creditor_query['values'] as $creditor) {
      $creditor_list[$creditor['id']] = $creditor['name'];
    }
    return $creditor_list;
  }

  /**
   * Get list of frequencies
   */
  protected function getFrequencies() {
    return [
      1  => E::ts('annually'),
      2  => E::ts('semi-annually'),
      4  => E::ts('quarterly'),
      6  => E::ts('bi-monthly'),
      12 => E::ts('monthly'),
    ];
  }

  /**
   * Get a list of all creditors
   */
  protected function getCreditors() {
    $creditor_list = [];
    $creditor_query = \civicrm_api3('SepaCreditor', 'get', ['option.limit' => 0]);
    foreach ($creditor_query['values'] as $creditor) {
      $creditor_list[$creditor['id']] = $creditor['name'];
    }
    return $creditor_list;
  }

  /**
   * Get a list of all financial types
   */
  protected function getFinancialTypes() {
    $list = [];
    $query = \civicrm_api3('FinancialType', 'get', [
      'option.limit' => 0,
      'is_enabled'   => 1,
      'return'       => 'id,name',
    ]);
    foreach ($query['values'] as $entity) {
      $list[$entity['id']] = $entity['name'];
    }
    return $list;
  }

  /**
   * Get a list of all campaigns
   */
  protected function getCampaigns() {
    $list = [];
    $query = \civicrm_api3('Campaign', 'get', [
      'option.limit' => 0,
      'is_active'    => 1,
      'return'       => 'id,title',
    ]);
    foreach ($query['values'] as $entity) {
      $list[$entity['id']] = $entity['title'];
    }
    return $list;
  }

  /**
   * Get list of collection days
   */
  protected function getCollectionDays() {
    $list = range(0, 28);
    $options = array_combine($list, $list);
    $options[0] = E::ts('as soon as possible');
    return $options;
  }

  /**
   * Select the cycle day from the given creditor,
   *  that allows for the soonest collection given the buffer time
   *
   * @param array $mandate_data
   *      all data known about the mandate
   *
   */
  protected function calculateSoonestCycleDay($mandate_data) {
    // get creditor ID
    $creditor_id = (int) $mandate_data['creditor_id'];
    if (!$creditor_id) {
      $default_creditor = \CRM_Sepa_Logic_Settings::defaultCreditor();
      if ($default_creditor) {
        $creditor_id = $default_creditor->id;
      }
      else {
        \Civi::log()->notice(
          'CreateRecurringMandate action: No creditor, and no default creditor set! Using cycle day 1'
        );
        return 1;
      }
    }

    // get start date
    $date = strtotime(\CRM_Utils_Array::value('start_date', $mandate_data, date('Y-m-d')));

    // get cycle days
    $cycle_days = \CRM_Sepa_Logic_Settings::getListSetting('cycledays', range(1, 28), $creditor_id);

    // iterate through the days until we hit a cycle day
    for ($i = 0; $i < 31; $i++) {
      if (in_array(date('j', $date), $cycle_days)) {
        // we found our cycle_day!
        return date('j', $date);
      }
      else {
        // no? try the next one...
        $date = strtotime('+ 1 day', $date);
      }
    }

    // no hit? that shouldn't happen...
    return 1;
  }

  protected function yesNoOptions() {
    return [
      0  => E::ts('no'),
      1  => E::ts('yes'),
    ];
  }

}
