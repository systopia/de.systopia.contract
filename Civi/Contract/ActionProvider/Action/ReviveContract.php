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

class ReviveContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
        new Specification('default_membership_type_id',       'Integer', E::ts('Membership Type ID (default)'), true, null, null, $this->getMembershipTypes(), false),
        new Specification('config_defer_payment_start',               'Boolean', E::ts('Defer Payment Start'), false, false),
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
        new Specification('contact_id', 'Integer', E::ts('Contact ID'), false),
        new Specification('contract_id', 'Integer', E::ts('Contract ID'), true),
        new Specification('date',            'Date', E::ts('Date'),  true, date('Y-m-d H:i:s')),
        new Specification('membership_type_id',       'Integer', E::ts('Membership Type ID'), false),
        new Specification('defer_payment_start', 'Boolean', E::ts('Defer Payment Start'), false),
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
    $contract_data = ['action' => 'revive'];

    // add basic fields to contract_data
    foreach (['contact_id','contract_id','membership_type_id','date'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $contract_data[$parameter_name] = $value;
      }
    }
    // add override fields to contract_data
    foreach (['membership_type_id'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $contract_data[$parameter_name] = $value;
    }

    // add defer_payment_start
    $defer_payment_start = $parameters->getParameter('defer_payment_start');
    if(!empty($defer_payment_start)){
        $contract_data['membership_payment.defer_payment_start'] = $defer_payment_start;
    }
    $config_defer_payment_start = $this->configuration->getParameter("config_defer_payment_start");
    if(!empty($config_defer_payment_start)){
        $contract_data['membership_payment.defer_payment_start'] = $config_defer_payment_start;
    }


    // create mandate
    try {
      $contract = \civicrm_api3('Contract', 'modify', $contract_data);
      $output->setParameter('contract_id', $contract['id']);
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

}