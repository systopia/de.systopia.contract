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

use Civi\Api4\Campaign;
use Civi\Api4\FinancialType;
use Civi\Api4\MembershipType;
use Civi\Api4\SepaCreditor;
use CRM_Contract_ExtensionUtil as E;

class CreateContract extends AbstractAction {

  /**
   * Returns the specification of the configuration options for the actual action.
   *
   * @return \Civi\ActionProvider\Parameter\SpecificationBag specs
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
      new Specification(
        'default_membership_type_id',
        'Integer',
        E::ts('Membership Type ID (default)'),
        TRUE,
        NULL,
        NULL,
        $this->getMembershipTypes(),
        FALSE
      ),
      new Specification(
        'default_creditor_id',
        'Integer',
        E::ts('Creditor (default)'),
        TRUE,
        NULL,
        NULL,
        $this->getCreditors(),
        FALSE
      ),
      new Specification(
        'default_financial_type_id',
        'Integer',
        E::ts('Financial Type (default)'),
        TRUE,
        NULL,
        NULL,
        $this->getFinancialTypes(),
        FALSE
      ),
      new Specification(
        'default_campaign_id',
        'Integer',
        E::ts('Campaign (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getCampaigns(),
        FALSE
      ),
      new Specification(
        'default_frequency',
        'Integer',
        E::ts('Frequency (default)'),
        TRUE,
        12,
        NULL,
        $this->getFrequencies()
      ),
      new Specification(
        'default_cycle_day',
        'Integer',
        E::ts('Collection Day (default)'),
        FALSE,
        0,
        NULL,
        $this->getCollectionDays()
      ),
      new Specification('buffer_days', 'Integer', E::ts('Buffer Days'), TRUE, 7),
      new Specification(
        'prevent_multiple_contracts',
        'Integer',
        E::ts('If a Contract already exists'),
        FALSE,
        0,
        NULL,
        $this->getMultipleContractOptions()
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
      new Specification('membership_type_id', 'Integer', E::ts('Membership Type ID'), FALSE),
      new Specification('iban', 'String', E::ts('IBAN'), TRUE),
      new Specification('bic', 'String', E::ts('BIC'), TRUE),
      new Specification('amount', 'Money', E::ts('Amount'), TRUE),
      new Specification('reference', 'String', E::ts('Mandate Reference'), FALSE),

        // recurring information
      new Specification('frequency', 'Integer', E::ts('Frequency'), FALSE, 12, NULL, $this->getFrequencies()),
      new Specification('cycle_day', 'Integer', E::ts('Collection Day'), FALSE, 1, NULL, $this->getCollectionDays()),

        // basic overrides
      new Specification(
        'creditor_id',
        'Integer',
        E::ts('Creditor (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getCreditors(),
        FALSE
      ),
      new Specification(
        'financial_type_id',
        'Integer',
        E::ts('Financial Type (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getFinancialTypes(),
        FALSE
      ),
      new Specification(
        'campaign_id',
        'Integer',
        E::ts('Campaign (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getCampaigns(),
        FALSE
      ),

      // dates
      new Specification('start_date', 'Date', E::ts('Start Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('join_date', 'Date', E::ts('Member Since'), FALSE, date('Y-m-d')),
      new Specification('date', 'Date', E::ts('Signature Date'), FALSE, date('Y-m-d H:i:s')),
      new Specification('validation_date', 'Date', E::ts('Validation Date'), FALSE, date('Y-m-d H:i:s')),

        # Contract stuff
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
    if (
      1 === $this->configuration->getParameter('prevent_multiple_contracts')
      && \CRM_Contract_Utils::getActiveContractCount($parameters->getParameter('contact_id')) > 0
    ) {
      $output->setParameter('mandate_id', '');
      $output->setParameter('mandate_reference', '');
      $output->setParameter('contract_id', '');
      $output->setParameter('error', E::ts('Contract already exists'));
      return;
    }

    // create mandate
    try {
      $mandate_data = $this->buildMandateDate($parameters);
      /** @phpstan-var array<string, mixed> $mandate */
      $mandate = \civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      /** @phpstan-var array<string, mixed> $mandate */
      $mandate = \civicrm_api3('SepaMandate', 'getsingle', [
        'id' => $mandate['id'],
        'return' => 'id,entity_id,reference',
      ]);

      $contract_data = $this->buildContractData($parameters, $mandate);
      /** @phpstan-var array<string, mixed> $contract */
      $contract = \civicrm_api3('Contract', 'create', $contract_data);

      $output->setParameter('mandate_id', $mandate['id']);
      $output->setParameter('mandate_reference', $mandate['reference']);
      $output->setParameter('contract_id', $contract['id']);
    }
    catch (\Exception $ex) {
      $output->setParameter('mandate_id', '');
      $output->setParameter('mandate_reference', '');
      $output->setParameter('contract_id', '');
      $output->setParameter('error', $ex->getMessage());
    }
  }

  /**
   * @return array<int, string>
   */
  protected function getMembershipTypes(): array {
    return MembershipType::get(FALSE)
      ->addSelect('id', 'name')
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @return array<int, string>
   */
  protected function getFrequencies(): array {
    return [
      1  => E::ts('annually'),
      2  => E::ts('semi-annually'),
      4  => E::ts('quarterly'),
      6  => E::ts('bi-monthly'),
      12 => E::ts('monthly'),
    ];
  }

  /**
   * @return array<int, string>
   */
  protected function getCreditors(): array {
    return SepaCreditor::get(FALSE)
      ->addSelect('id', 'name')
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @return array<int, string>
   */
  protected function getFinancialTypes(): array {
    return FinancialType::get(FALSE)
      ->addSelect('id', 'name')
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @return array<int, string>
   */
  protected function getCampaigns(): array {
    return Campaign::get(FALSE)
      ->addSelect('id', 'name')
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @return array<int<0, 28>, int<1, 28>|string>
   */
  protected function getCollectionDays(): array {
    $list = range(0, 28);
    $options = array_combine($list, $list);
    $options[0] = E::ts('as soon as possible');
    return $options;
  }

  /**
   * Select the cycle day from the given creditor,
   *  that allows for the soonest collection given the buffer time
   *
   * @param array<string, mixed> $mandate_data
   *      all data known about the mandate
   */
  protected static function calculateSoonestCycleDay(array $mandate_data): int {
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
    $date = strtotime($mandate_data['start_date'] ?? date('Y-m-d'));

    // get cycle days
    /** @phpstan-var list<int> $cycleDays */
    // TODO: Is this safe to return a list of integers only?
    $cycleDays = \CRM_Sepa_Logic_Settings::getListSetting('cycledays', range(1, 28), $creditor_id);

    // iterate through the days until we hit a cycle day
    for ($i = 0; $i < 31; $i++) {
      $cycleDay = (int) date('j', $date);
      if (in_array($cycleDay, $cycleDays)) {
        return $cycleDay;
      }
      $date = strtotime('+ 1 day', $date);
    }

    return 1;
  }

  /**
   * @return array<int, string>
   */
  protected function getMultipleContractOptions(): array {
    return [
      0  => E::ts('ignore'),
      1  => E::ts('show error message'),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  protected function buildMandateDate(ParameterBagInterface $parameters): array {
    $mandateData = ['type' => 'RCUR'];

    // add basic fields to mandate_data
    foreach ([
      'contact_id',
      'iban',
      'bic',
      'reference',
      'amount',
      'start_date',
      'date',
      'validation_date',
      'account_holder',
    ] as $parameterName) {
      $value = $parameters->getParameter($parameterName);
      if (NULL !== $value && '' !== $value) {
        $mandateData[$parameterName] = $value;
      }
    }

    // add override fields to mandate_data
    foreach (['creditor_id', 'financial_type_id', 'campaign_id', 'cycle_day', 'frequency'] as $parameterName) {
      $value = $parameters->getParameter($parameterName);
      if (NULL === $value || '' === $value) {
        $value = $this->configuration->getParameter("default_{$parameterName}");
      }
      $mandateData[$parameterName] = $value;
    }

    // sort out frequency
    $mandateData['frequency_interval'] = 12 / $mandateData['frequency'];
    $mandateData['frequency_unit'] = 'month';
    unset($mandateData['frequency']);

    // verify/adjust start date
    $buffer_days = (int) $this->configuration->getParameter('buffer_days');
    $earliestStartDate = strtotime("+ {$buffer_days} days");
    $currentStartDate = strtotime($mandateData['start_date']);
    if (
      FALSE !== $earliestStartDate && FALSE !== $currentStartDate
      && $currentStartDate < $earliestStartDate
    ) {
      $mandateData['start_date'] = date('YmdHis', $earliestStartDate);
    }

    // if not set, calculate the closest cycle day
    if (NULL === $mandateData['cycle_day'] || '' === $mandateData['cycle_day']) {
      $mandateData['cycle_day'] = static::calculateSoonestCycleDay($mandateData);
    }

    return $mandateData;
  }

  /**
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $parameters
   * @param array<string, mixed> $mandate
   *
   * @return array<string, mixed>
   */
  protected function buildContractData(ParameterBagInterface $parameters, array $mandate): array {
    $contractData = [];
    // add basic fields to contract_data
    foreach (['contact_id', 'membership_type_id', 'start_date', 'join_date'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (!empty($value)) {
        $contractData[$parameter_name] = $value;
      }
    }
    // add override fields to contract_data
    foreach (['membership_type_id'] as $parameter_name) {
      $value = $parameters->getParameter($parameter_name);
      if (empty($value)) {
        $value = $this->configuration->getParameter("default_{$parameter_name}");
      }
      $contractData[$parameter_name] = $value;
    }

    // add account holder
    $account_holder = $parameters->getParameter('account_holder');
    if (!empty($account_holder)) {
      $contractData['membership_payment.from_name'] = $account_holder;
    }

    if (empty($contractData['join_date']) and (!empty($contractData['start_date']))) {
      $contractData['join_date'] = $contractData['start_date'];
    }

    $contractData['membership_payment.membership_recurring_contribution'] = $mandate['entity_id'];

    return $contractData;
  }

}
