<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * "Upgrade Membership" change
 */
class CRM_Contract_Change_Upgrade extends CRM_Contract_Change {

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return
   */
  public function getRequiredFields(): array {
    return [];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    if ($this->isNew()) {
      $contract = $this->getContract(TRUE);
      $contract_after_execution = $contract;

      // copy submitted changes to change activity
      foreach (CRM_Contract_Change::FIELD_MAPPING_CHANGE_CONTRACT as $contract_attribute => $change_attribute) {
        if (!empty($this->data[$contract_attribute])) {
          $this->data[$change_attribute] = $this->data[$contract_attribute];
          $contract_after_execution[$contract_attribute] = $this->data[$contract_attribute];
        }
      }

      $this->data['subject'] = $this->getSubject($contract_after_execution, $contract);
    }
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute(): void {
    $contract_before = $this->getContract(TRUE);

    // compile upgrade
    $contract_update = [];

    // adjust membership type?
    $membership_type_update = $this->getParameter('contract_updates.ch_membership_type');
    if ($membership_type_update) {
      if ($contract_before['membership_type_id'] != $membership_type_update) {
        $contract_update['membership_type_id'] = $membership_type_update;
      }
    }
    else {
      // FIXME: replicating weird behaviour by old engine
      $this->setParameter('contract_updates.ch_membership_type', $contract_before['membership_type_id']);
    }

    // check payemnt instrument for the new contract
    // TODO: Account for changes of payment instrument when SEPA is not involved.
    $payment_instrument_update = $this->getParameter('contract_updates.ch_payment_instrument');
    if (NULL !== $payment_instrument_update) {
      if ($contract_before['membership_payment.payment_instrument'] != $payment_instrument_update) {
        $contract_update['membership_payment.payment_instrument'] = $payment_instrument_update;
      }
    }
    $payment_types = CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);
    if (
      isset($contract_update['membership_payment.payment_instrument'])
      && CRM_Contract_RecurringContribution::isSepaPaymentInstrument(
        $contract_before['membership_payment.payment_instrument']
      )
      && !CRM_Contract_RecurringContribution::isSepaPaymentInstrument(
        $contract_update['membership_payment.payment_instrument']
      )
    ) {
      // Terminate mandate if payment instrument changed from SEPA to anything else.
      CRM_Contract_SepaLogic::terminateSepaMandate(
        $contract_before['membership_payment.membership_recurring_contribution']
      );

      // Create new recurring contribution for new payment method.
      $completeContractUpdate = $contract_update + $contract_before;

      $frequency = $this->getParameter('contract_updates.ch_frequency')
        ?? $contract_before['membership_payment.membership_frequency'];

      $annual_amount = $this->getParameter('contract_updates.ch_annual')
        ?? $contract_before['membership_payment.membership_annual'];

      $frequencyInterval = 12 / $frequency;
      $amount = CRM_Contract_SepaLogic::formatMoney(CRM_Contract_SepaLogic::formatMoney($annual_amount) / $frequency);
      if ($amount < 0.01) {
        // TODO: Exception being thrown does not invalidate the modify form.
        throw new Exception('Installment amount too small');
      }

      $startDate = CRM_Contract_SepaLogic::getMandateUpdateStartDate($contract_before, $this->data, $this->data);

      $accountHolder = $this->getParameter('contract_updates.ch_from_name')
        ?? $contract_before['membership_payment.from_name'];

      $cycleDay = (int) ($this->getParameter('contract_updates.ch_cycle_day')
        ?? $contract_before['membership_payment.cycle_day']);

      if (isset($contract_before['membership_payment.membership_recurring_contribution'])) {
        $recurringContribution = civicrm_api3(
          'ContributionRecur',
          'getsingle',
          ['id' => $contract_before['membership_payment.membership_recurring_contribution']]
        );
      }
      $campaignId = $this->getParameter('campaign_id') ?? $recurringContribution['campaign_id'] ?? NULL;
      $campaignId = is_numeric($campaignId) ? (int) $campaignId : NULL;

      $new_payment_contract = CRM_Contract_RecurringContribution::createRecurringContribution(
        (int) $completeContractUpdate['contact_id'],
        $amount,
        $startDate,
        $accountHolder,
        array_search($completeContractUpdate['membership_payment.payment_instrument'], $payment_types),
        $cycleDay,
        $frequencyInterval,
        $campaignId
      );
      CRM_Contract_SepaLogic::setContractPaymentLink($this->getContractID(), (int) $new_payment_contract);
    }
    else {
      // adjust mandate/payment mode?
      $new_payment_contract = CRM_Contract_SepaLogic::updateSepaMandate(
        $this->getContractID(),
        $contract_before,
        $this->data,
        $this->data,
        $this->getActionName()
      );
    }

    if ($new_payment_contract) {
      // this means a new mandate has been created -> set
      $contract_update['membership_payment.membership_recurring_contribution'] = $new_payment_contract;
    }

    // perform the update
    $this->updateContract($contract_update);

    // update change activity
    $contract_after = $this->getContract();
    foreach (CRM_Contract_Change::FIELD_MAPPING_CHANGE_CONTRACT as $membership_field => $change_field) {
      // copy fields
      if (isset($contract_after[$membership_field])) {
        $this->setParameter($change_field, $contract_after[$membership_field]);
      }
    }
    $this->setParameter(
      'contract_updates.ch_annual_diff',
      (float) $contract_after['membership_payment.membership_annual']
      - (float) $contract_before['membership_payment.membership_annual']
    );
    $this->setParameter('subject', $this->getSubject($contract_after, $contract_before));
    $this->setParameter('contract_updates.ch_from_name', $contract_after['membership_payment.from_name']);
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // TODO:
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
      // FIXME: replicating weird behaviour by old engine
      $contract_before = [];
      unset($contract_after['membership_type_id']);
      unset($contract_after['membership_payment.from_ba']);
      unset($contract_after['membership_payment.to_ba']);
      unset($contract_after['membership_payment.defer_payment_start']);
      unset($contract_after['membership_payment.payment_instrument']);
      unset($contract_after['membership_payment.cycle_day']);
    }

    // calculate differences
    $differences        = [];
    $field2abbreviation = [
      'membership_type_id'                      => 'type',
      'membership_payment.membership_annual'    => 'amt.',
      'membership_payment.membership_frequency' => 'freq.',
      'membership_payment.to_ba'                => 'gp iban',
      'membership_payment.from_ba'              => 'member iban',
      'membership_payment.cycle_day'            => 'cycle day',
      'membership_payment.payment_instrument'   => 'payment method',
      'membership_payment.defer_payment_start'  => 'defer',
    ];

    foreach ($field2abbreviation as $field_name => $subject_abbreviation) {
      $raw_value_before = CRM_Utils_Array::value($field_name, $contract_before);
      $value_before     = $this->labelValue($raw_value_before, $field_name);
      $raw_value_after  = CRM_Utils_Array::value($field_name, $contract_after);
      $value_after      = $this->labelValue($raw_value_after, $field_name);

      // FIXME: replicating weird behaviour by old engine

      // standard behaviour:
      if ($value_before != $value_after) {
        $differences[] = "{$subject_abbreviation} {$value_before} to {$value_after}";
      }
    }

    $contract_id = $this->getContractID();
    $subject = "id{$contract_id}: " . implode(' AND ', $differences);

    // FIXME: replicating weird behaviour by old engine
    return preg_replace('/  to/', ' to', $subject);
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['Grace', 'Current'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts('Update Contract');
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param $links                array  currently given links
   * @param $current_status_name  string membership status as a string
   * @param $membership_data      array  all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    if (in_array($current_status_name, self::getStartStatusList())) {
      $links[] = [
        'name'  => E::ts('Update'),
        'title' => self::getChangeTitle(),
        'url'   => 'civicrm/contract/modify',
        'bit'   => CRM_Core_Action::UPDATE,
        'qs'    => 'modify_action=update&id=%%id%%',
        'weight' => 10,
      ];
    }
  }

}
