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
use CRM_Contract_Utils as U;

class GetContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
        new Specification('default_membership_type_id',       'Integer', E::ts('Membership Type ID (default)'), false, null, null, $this->getMembershipTypes(), false),
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
    $specifications = [
      new Specification('contract_id',        'String', E::ts('Contract ID'), false, null, null, null, false),
      new Specification('error',             'String',  E::ts('Error Message (if failed)'), false, null, null, null, false),
      new Specification('join_date',        'Date', E::ts('Join Date'), false, null, null, null, false),
      new Specification('start_date',        'Date', E::ts('Start Date'), false, null, null, null, false),

      # Contract custom Fields
      new Specification('membership_annual',        'Money', E::ts('Annual Membership Contribution'), false, null, null, null, false),
      new Specification('membership_frequency',        'Integer', E::ts('Payment Interval'), false, null, null, null, false),
      new Specification('membership_recurring_contribution',        'Integer', E::ts('Recurring contribution/mandate'), false, null, null, null, false),
      new Specification('from_ba',        'Integer', E::ts('Donor\'s Bank Account'), false, null, null, null, false),
      new Specification('cycle_day',        'Integer', E::ts('Cycle day'), false, null, null, null, false),
      new Specification('payment_instrument',        'Integer', E::ts('Payment method'), false, null, null, null, false),
      new Specification('defer_payment_start',        'Integer', E::ts('Defer Payment Start'), false, null, null, null, false),

      # Recurring Contribution
      new Specification('amount',        'Date', E::ts('Amount'), false, null, null, null, false),
      new Specification('currency',        'Date', E::ts('Currency'), false, null, null, null, false),
      new Specification('frequency_unit',        'Date', E::ts('Frequency Unit'), false, null, null, null, false),
      new Specification('frequency_interval',        'Date', E::ts('Frequency Interval'), false, null, null, null, false),
      new Specification('iban',        'Date', E::ts('IBAN'), false, null, null, null, false),

    ];

    # get custom fields of Contract
    #$customGroupNames = [ 'membership_payment'];
    #$customGroups     = civicrm_api3('CustomGroup', 'get', ['name' => ['IN' => $customGroupNames], 'return' => 'name', 'options' => ['limit' => 1000]])['values'];
    #$customFields     = civicrm_api3('CustomField', 'get', ['custom_group_id' => ['IN' => $customGroupNames], 'options' => ['limit' => 1000]]);
    #foreach ($customFields['values'] as $c) {
    #    if (strcmp($c['name'],'membership_annual') or strcmp($c['name'],'membership_frequency') or strcmp($c['name'],'membership_recurring_contribution') or strcmp($c['name'],'from_ba')or strcmp($c['name'],'cycle_day') or strcmp($c['name'],'payment_instrument') or strcmp($c['name'],'defer_payment_start')){
    #        # do nothing
    #    }else{
    #        $specifications[] = new Specification($c['name'],        'String', E::ts($c['label']), false, null, null, null, false);##
    #
    #       #$specifications[] = new Specification($c['name'],        $this->getDataType($c['data_type']), E::ts($c['label']), false, null, null, null, false);
    #       #$specifications[] = new Specification(U::getCustomFieldName("custom_"+$c['id']),        'Date', U::getCustomFieldName("custom_"+$c['id']), false, null, null, null, false);
    #      #$specifications[] = new Specification($c['name'],        'Date', E::ts($c['label']), false, null, null, null, false);#
    #
    #    }
    #}

    return new SpecificationBag($specifications);
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
    $contract_data = ['active_only' => 1];
    // add basic fields to contract_data
    foreach (['contact_id','membership_type_id'] as $parameter_name) {
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

    if (!function_exists('str_starts_with')) {
      function str_starts_with($str, $start) {
        return (@substr_compare($str, $start, 0, strlen($start))==0);
      }
    }
    // get contract
    try {
      $contract = \civicrm_api3('Contract', 'getSingle', $contract_data);

      $recurring_contribution = \civicrm_api3('ContributionRecur', 'getSingle', ['id' => $contract[U::getCustomFieldId('membership_payment.membership_recurring_contribution')]]);


      $output->setParameter('contract_id', $contract['id']);
      $output->setParameter('join_date', $contract['join_date']);
      $output->setParameter('start_date', $contract['start_date']);


      #
      #if (intval($contract['membership_frequency']) != 0){
      #  $output->setParameter('membership_amount_per_frequency', ((float)str_replace(',','.',$contract['membership_annual'])/(int)$contract['membership_frequency']));
      #}else{
      #  $output->setParameter('membership_amount_per_frequency', '');
      #}
      $output->setParameter('membership_annual', $contract[U::getCustomFieldId('membership_payment.membership_annual')]);
      $output->setParameter('membership_frequency', $contract[U::getCustomFieldId('membership_payment.membership_frequency')]);
      $output->setParameter('membership_recurring_contribution', $contract[U::getCustomFieldId('membership_payment.membership_recurring_contribution')]);
      $output->setParameter('from_ba', $contract[U::getCustomFieldId('membership_payment.from_ba')]);
      $output->setParameter('cycle_day', $contract[U::getCustomFieldId('membership_payment.cycle_day')]);
      $output->setParameter('payment_instrument', $contract[U::getCustomFieldId('membership_payment.payment_instrument')]);
      $output->setParameter('defer_payment_start', $contract[U::getCustomFieldId('membership_payment.defer_payment_start')]);

      $output->setParameter('amount', $recurring_contribution['amount']);
      $output->setParameter('currency', $recurring_contribution['currency']);
      $output->setParameter('frequency_unit', $recurring_contribution['frequency_unit']);
      $output->setParameter('frequency_interval', $recurring_contribution['frequency_interval']);

      $bankaccount_reference = \civicrm_api3('BankingAccountReference', 'getSingle', ['ba_id' => $contract[U::getCustomFieldId('membership_payment.from_ba')], 'reference_type_id' => U::lookupOptionValue('civicrm_banking.reference_types', 'iban', 'id'), 'return' => ["reference"]]);
      $output->setParameter('iban', $bankaccount_reference['reference']);
      #$output->setParameter('membership_type_id', $contract['membership_type_id']);
      #$output->setParameter('status_id', $contract['status_id']);
      #$output->setParameter('is_test', $contract['is_test']);
      #$output->setParameter('membership_name', $contract['membership_name']);
      #$output->setParameter('relationship_name', $contract['relationship_name']);

      #foreach ($contract as $param => $value){
      #  if (str_starts_with($param, "custom_") and (substr_count($param, '_') == 1)){
      #          $name = U::getCustomFieldName($param);
      #          $name = substr($name,strpos($name,".")+1);
      #          $output->setParameter($name, $contract[$param]);
      #  }
      #}

      $output->setParameter('error', "");

    } catch (\Exception $ex) {
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
  * map the datatype of custom field to a data type of formprocessor
  */
  protected function getDataType($customFieldDataType){
    $dataTypeMapping = [
        "Int" => "Integer",
        "String" => "String",
        "Date" => "Date",
    ];
    return $dataTypeMapping[$customFieldDataType];
  }

}