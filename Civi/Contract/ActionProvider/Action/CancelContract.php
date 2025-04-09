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

use Civi\ActionProvider\Action\AbstractAction;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\ActionProvider\Parameter\Specification;
use Civi\ActionProvider\Parameter\SpecificationBag;

use CRM_Contract_ExtensionUtil as E;

class CancelContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
      new Specification('default_membership_type_id', 'Integer', E::ts('Membership Type ID (default)'), TRUE, NULL, NULL, $this->getMembershipTypes(), FALSE),
      new Specification('default_membership_cancellation.membership_cancel_reason', 'Integer', E::ts('Cancel Reason (default)'), TRUE, NULL, NULL, $this->getCancelReasons(), FALSE),

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
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), FALSE),
      new Specification('contract_id', 'Integer', E::ts('Contract ID'), TRUE),
      new Specification('date', 'Date', E::ts('Date'), TRUE, date('Y-m-d H:i:s')),
      new Specification('membership_type_id', 'Integer', E::ts('Membership Type ID'), FALSE),
      new Specification('membership_cancellation.membership_cancel_reason', 'Integer', E::ts('Cancel Reason'), FALSE),

        // dates

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
      new Specification('contract_id', 'Integer', E::ts('Contract ID'), FALSE, NULL, NULL, NULL, FALSE),
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
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output) {
    $contract_data = ['action' => 'cancel'];

    // add basic fields to contract_data
    foreach (['contact_id', 'contract_id', 'membership_type_id', 'date', 'membership_cancellation.membership_cancel_reason'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $contract_data[$parameter_name] = $value;
      }
    }
    // add override fields to contract_data
    foreach (['membership_type_id', 'membership_cancellation.membership_cancel_reason'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $contract_data[$parameter_name] = $value;
    }

    // create mandate
    try {
      $contract = \civicrm_api3('Contract', 'modify', $contract_data);
      $output->setParameter('contract_id', $contract['id']);
    }
    catch (\Exception $ex) {
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
   * Get a list of cancel reasons
   */
  protected function getCancelReasons() {
    $cancel_reasons = [];
    $query = \civicrm_api3('OptionValue', 'get', ['option_group_id' => 'contract_cancel_reason', 'option.limit' => 0]);
    foreach ($query['values'] as $reason) {
      $cancel_reasons[$reason['value']] = $reason['name'];
    }
    return $cancel_reasons;
  }

}
