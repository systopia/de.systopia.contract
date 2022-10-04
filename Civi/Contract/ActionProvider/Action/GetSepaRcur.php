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

class GetSepaRcur extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   *
   * @return SpecificationBag specs
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
        // required fields
        #new Specification('contact_id', 'Integer', E::ts('Contact ID'), true),
        new Specification('recurring_contribution_id',       'Integer', E::ts('Recurring Contribution ID'), true),
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
      new Specification('reference',        'String', E::ts('Mandate Reference'), false, null, null, null, false),
      new Specification('iban',        'Date', E::ts('IBAN'), false, null, null, null, false),
      new Specification('bic',        'Date', E::ts('BIC'), false, null, null, null, false),
      new Specification('error',             'String',  E::ts('Error Message (if failed)'), false, null, null, null, false),
    ];

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

    // get contract
    $mandate_params = ['entity_table' => 'civicrm_contribution_recur'];
    $mandate_params['entity_id'] = $parameters->getParameter('recurring_contribution_id');

    try {
      $mandate = \civicrm_api3('SepaMandate', 'getSingle', $mandate_params);

      $output->setParameter('iban', $mandate['iban']);
      $output->setParameter('bic', $mandate['bic']);
      $output->setParameter('error', "");

    } catch (\Exception $ex) {
      $output->setParameter('iban', '');
      $output->setParameter('bic', '');
      $output->setParameter('error', $ex->getMessage());
    }
  }
}