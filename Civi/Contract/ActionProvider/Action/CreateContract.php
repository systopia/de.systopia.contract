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

namespace Civi\Contract\ActionProvider\Action;

use \Civi\ActionProvider\Action\AbstractAction;
use \Civi\ActionProvider\Parameter\ParameterBagInterface;
use \Civi\ActionProvider\Parameter\Specification;
use \Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Contract_ExtensionUtil as E;

class CreateContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
        new Specification('default_membership_type_id',       'Integer', E::ts('Membership Type ID (default)'), true, null, null, $this->getMembershipTypes(), false),
        new Specification('default_creditor_id',       'Integer', E::ts('Creditor (default)'), true, null, null, $this->getCreditors(), false),
        new Specification('default_financial_type_id', 'Integer', E::ts('Financial Type (default)'), true, null, null, $this->getFinancialTypes(), false),
        new Specification('default_campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),
        new Specification('default_frequency',         'Integer', E::ts('Frequency (default)'), true, 12, null, $this->getFrequencies()),
        new Specification('default_cycle_day',         'Integer', E::ts('Collection Day (default)'), false, 0, null, $this->getCollectionDays()),
        new Specification('buffer_days',               'Integer', E::ts('Buffer Days'), true, 7),
        new Specification('prevent_multiple_contracts',         'Integer', E::ts('If a Contract already exists'), false, 0, null, $this->getMultipleContractOptions()),

    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
        new Specification('contact_id', 'Integer', E::ts('Contact ID'), true),
        new Specification('membership_type_id',       'Integer', E::ts('Membership Type ID'), false),
        new Specification('iban',       'String',  E::ts('IBAN'), true),
        new Specification('bic',        'String',  E::ts('BIC'), true),
        new Specification('amount',     'Money',   E::ts('Amount'), true),
        new Specification('reference',  'String',  E::ts('Mandate Reference'), false),

        // recurring information
        new Specification('frequency',  'Integer', E::ts('Frequency'),      false, 12, null, $this->getFrequencies()),
        new Specification('cycle_day',  'Integer', E::ts('Collection Day'), false, 1,  null, $this->getCollectionDays()),

        // basic overrides
        new Specification('creditor_id',       'Integer', E::ts('Creditor (default)'), false, null, null, $this->getCreditors(), false),
        new Specification('financial_type_id', 'Integer', E::ts('Financial Type (default)'), false, null, null, $this->getFinancialTypes(), false),
        new Specification('campaign_id',       'Integer', E::ts('Campaign (default)'), false, null, null, $this->getCampaigns(), false),

        // dates
        new Specification('start_date',      'Date', E::ts('Start Date'), false, date('Y-m-d H:i:s')),
        new Specification('join_date',      'Date', E::ts('Member Since'), false, date('Y-m-d')),
        new Specification('date',            'Date', E::ts('Signature Date'),  false, date('Y-m-d H:i:s')),
        new Specification('validation_date', 'Date', E::ts('Validation Date'), false, date('Y-m-d H:i:s')),

        # Contract stuff
        new Specification('account_holder',       'String',  E::ts('Members Bank Account'), false),
        #new Specification('membership_payment.to_ba',       'String',  E::ts('IBAN'), true),
        #new Specification('membership_payment.membership_annual',      'Money',  E::ts('Anual Amount'), false),
        #new Specification('membership_payment.membership_frequency',      'Integer',  E::ts('Frequency'), false),
        #new Specification('membership_payment.cycle_day',      'Integer',  E::ts('Cycle Day'), false),
        #new Specification('membership_type_id',       'Integer', E::ts('Membership Type ID'), false),
    ]);
  }

  /**
   * Returns the specification of the output parameters of this action.
   *
   * This function could be overridden by child classes.
   *
   * @return SpecificationBag specs
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('mandate_id',        'Integer', E::ts('Mandate ID'), false, null, null, null, false),
      new Specification('mandate_reference', 'String',  E::ts('Mandate Reference'), false, null, null, null, false),
      new Specification('contract_id',        'Integer', E::ts('Contract ID'), false, null, null, null, false),
      new Specification('error',             'String',  E::ts('Error Message (if creation failed)'), false, null, null, null, false),
    ]);
  }

  /**
   * Run the action
   *
   * @param ParameterBagInterface $parameters
   *   The parameters to this action.
   * @param ParameterBagInterface $output
   * 	 The parameters this action can send back
   * @return void
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $mandate_data = ['type' => 'RCUR'];

    // If a Contract already exists: show error
    if($this->configuration->getParameter('prevent_multiple_contracts') == 1){
        $getcount_params = ['contact_id' => $parameters->getParameter('contact_id'), 'active_only' => 1];
        $count = \civicrm_api3('Contract', 'getcount', $getcount_params);
        if ($count){
          $output->setParameter('mandate_id', '');
          $output->setParameter('mandate_reference', '');
          $output->setParameter('contract_id', '');
          $output->setParameter('error', E::ts("Contract already exists"));
          return;
        }
    }

    // add basic fields to mandate_data
    foreach (['contact_id', 'iban', 'bic', 'reference', 'amount', 'start_date', 'date', 'validation_date','account_holder'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $mandate_data[$parameter_name] = $value;
      }
    }

    // add override fields to mandate_data
    foreach (['creditor_id', 'financial_type_id', 'campaign_id', 'cycle_day', 'frequency'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $mandate_data[$parameter_name] = $value;
    }

    // sort out frequency
    $mandate_data['frequency_interval'] = 12 / $mandate_data['frequency'];
    $mandate_data['frequency_unit'] = 'month';
    unset($mandate_data['frequency']);

    // verify/adjust start date
    $buffer_days = (int) $this->configuration->getParameter('buffer_days');
    $earliest_start_date = strtotime("+ {$buffer_days} days");
    $current_start_date = strtotime($mandate_data['start_date']);
    if ($current_start_date < $earliest_start_date) {
      $mandate_data['start_date'] = date('YmdHis', $earliest_start_date);
    }

    // if not set, calculate the closest cycle day
    if (empty($mandate_data['cycle_day'])) {
      $mandate_data['cycle_day'] = $this->calculateSoonestCycleDay($mandate_data);
    }

    $contract_data = [];
    // add basic fields to contract_data
    foreach (['contact_id','membership_type_id','start_date','join_date'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $contract_data[$parameter_name] = $value;
      }
    }
    // add override fields to contract_data
    foreach (['membership_type_id',] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $contract_data[$parameter_name] = $value;
    }

    // add account holder
    $account_holder = $parameters->getParameter('account_holder');
    if(!empty($account_holder)){
        $contract_data['membership_payment.from_name'] = $account_holder;
    }

    // create mandate
    try {
      $mandate = \civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = \civicrm_api3('SepaMandate', 'getsingle', ['id' => $mandate['id'], 'return' => 'id,entity_id,reference']);
      $contract_data['membership_payment.membership_recurring_contribution'] = $mandate['entity_id'];
      if (empty($contract_data['join_date']) and (!empty($contract_data['start_date']))) {
        $contract_data['join_date'] = $contract_data['start_date'];
      }
      $contract = \civicrm_api3('Contract', 'create', $contract_data);
      $output->setParameter('mandate_id', $mandate['id']);
      $output->setParameter('mandate_reference', $mandate['reference']);
      $output->setParameter('contract_id', $contract['id']);
    } catch (\Exception $ex) {
      $output->setParameter('mandate_id', '');
      $output->setParameter('mandate_reference', '');
      $output->setParameter('contract_id', '');
      $output->setParameter('error', $ex->getMessage());
    }
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
        1  => E::ts("annually"),
        2  => E::ts("semi-annually"),
        4  => E::ts("quarterly"),
        6  => E::ts("bi-monthly"),
        12 => E::ts("monthly"),
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
        'return'       => 'id,name']);
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
        'return'       => 'id,title']);
    foreach ($query['values'] as $entity) {
      $list[$entity['id']] = $entity['title'];
    }
    return $list;
  }

  /**
   * Get list of collection days
   */
  protected function getCollectionDays() {
    $list = range(0,28);
    $options = array_combine($list, $list);
    $options[0] = E::ts("as soon as possible");
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
          } else {
              \Civi::log()->notice("CreateRecurringMandate action: No creditor, and no default creditor set! Using cycle day 1");
              return 1;
          }
      }

      // get start date
      $date = strtotime(\CRM_Utils_Array::value('start_date', $mandate_data, date('Y-m-d')));

      // get cycle days
      $cycle_days = \CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $creditor_id);

      // iterate through the days until we hit a cycle day
      for ($i = 0; $i < 31; $i++) {
        if (in_array(date('j', $date), $cycle_days)) {
            // we found our cycle_day!
            return date('j', $date);
        } else {
            // no? try the next one...
            $date = strtotime("+ 1 day", $date);
        }
      }

      // no hit? that shouldn't happen...
      return 1;
  }

  protected function getMultipleContractOptions() {
    return [
        0  => E::ts("ignore"),
        1  => E::ts("show error message"),
    ];
  }


}
