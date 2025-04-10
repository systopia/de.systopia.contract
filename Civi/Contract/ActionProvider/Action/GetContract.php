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
use CRM_Contract_Utils as U;

class GetContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   */
  public function getConfigurationSpecification(): SpecificationBag {
    return new SpecificationBag([
      new Specification(
        'default_membership_type_id',
        'Integer',
        E::ts('Membership Type ID (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getMembershipTypes(),
        FALSE
      ),
    ]);
  }

  /**
   * Returns the specification of the parameters of the actual action.
   */
  public function getParameterSpecification(): SpecificationBag {
    return new SpecificationBag([
        // required fields
      new Specification('contact_id', 'Integer', E::ts('Contact ID'), TRUE),
      new Specification('membership_type_id', 'Integer', E::ts('Membership Type ID'), FALSE),
      new Specification('contract_id', 'Integer', E::ts('Contract ID'), FALSE),

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
    $specifications = [
      new Specification(
        'contract_id',
        'String',
        E::ts('Contract ID'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'error',
        'String',
        E::ts('Error Message (if failed)'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'join_date',
        'Date',
        E::ts('Join Date'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'start_date',
        'Date',
        E::ts('Start Date'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),

      # Contract custom Fields
      new Specification(
        'membership_annual',
        'Money',
        E::ts('Annual Membership Contribution'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'membership_frequency',
        'Integer',
        E::ts('Payment Interval'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'membership_recurring_contribution',
        'Integer',
        E::ts('Recurring contribution/mandate'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'from_ba',
        'Integer',
        E::ts('Donor\'s Bank Account'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'cycle_day',
        'Integer',
        E::ts('Cycle day'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'payment_instrument',
        'Integer',
        E::ts('Payment method'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'defer_payment_start',
        'Integer',
        E::ts('Defer Payment Start'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),

      # Recurring Contribution
      new Specification(
        'amount',
        'Date',
        E::ts('Amount'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'currency',
        'Date',
        E::ts('Currency'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'frequency_unit',
        'Date',
        E::ts('Frequency Unit'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'frequency_interval',
        'Date',
        E::ts('Frequency Interval'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),
      new Specification(
        'iban',
        'String',
        E::ts('IBAN'),
        FALSE,
        NULL,
        NULL,
        NULL,
        FALSE
      ),

    ];

    return new SpecificationBag($specifications);
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
    $contract_data = ['active_only' => 1];
    // add basic fields to contract_data
    foreach (['contact_id', 'contract_id', 'membership_type_id'] as $parameter_name) {
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

    if (!function_exists('str_starts_with')) {

      function str_starts_with($str, $start) {
        return (@substr_compare($str, $start, 0, strlen($start)) == 0);
      }

    }
    // get contract
    try {
      $contract = \civicrm_api3('Contract', 'getSingle', $contract_data);

      $recurring_contribution = \civicrm_api3(
        'ContributionRecur',
        'getSingle',
        ['id' => $contract[U::getCustomFieldId('membership_payment.membership_recurring_contribution')]]
      );

      $output->setParameter('contract_id', $contract['id']);
      $output->setParameter('join_date', $contract['join_date']);
      $output->setParameter('start_date', $contract['start_date']);

      $output->setParameter(
        'membership_annual',
        $contract[U::getCustomFieldId('membership_payment.membership_annual')]
      );
      $output->setParameter(
        'membership_frequency',
        $contract[U::getCustomFieldId('membership_payment.membership_frequency')]
      );
      $output->setParameter(
        'membership_recurring_contribution',
        $contract[U::getCustomFieldId('membership_payment.membership_recurring_contribution')]
      );
      $output->setParameter('from_ba', $contract[U::getCustomFieldId('membership_payment.from_ba')]);
      $output->setParameter('cycle_day', $contract[U::getCustomFieldId('membership_payment.cycle_day')]);
      $output->setParameter(
        'payment_instrument',
        $contract[U::getCustomFieldId('membership_payment.payment_instrument')]
      );
      $output->setParameter(
        'defer_payment_start',
        $contract[U::getCustomFieldId('membership_payment.defer_payment_start')]
      );

      $output->setParameter('amount', $recurring_contribution['amount']);
      $output->setParameter('currency', $recurring_contribution['currency']);
      $output->setParameter('frequency_unit', $recurring_contribution['frequency_unit']);
      $output->setParameter('frequency_interval', $recurring_contribution['frequency_interval']);

      $bankaccount_reference = \civicrm_api3(
        'BankingAccountReference',
        'getSingle',
        [
          'ba_id' => $contract[U::getCustomFieldId('membership_payment.from_ba')],
          'reference_type_id' => U::lookupOptionValue('civicrm_banking.reference_types', 'iban', 'id'),
          'return' => ['reference'],
          'options' => ['sort' => 'id desc', 'limit' => 1],
        ]
      );
      $output->setParameter('iban', $bankaccount_reference['reference'] ?? '');

      $output->setParameter('error', '');

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
   * map the datatype of custom field to a data type of formprocessor
   */
  protected function getDataType($customFieldDataType) {
    $dataTypeMapping = [
      'Int' => 'Integer',
      'String' => 'String',
      'Date' => 'Date',
    ];
    return $dataTypeMapping[$customFieldDataType];
  }

}
