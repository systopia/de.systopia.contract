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
  private static $paymentTransitions = [
    'SEPA' => [
      'SEPA'    => 'terminate_create_sepa',
      'non-SEPA'=> 'terminate_create_nonsepa',
      'existing'=> 'terminate_assign_existing',
      'None'    => 'terminate_create_zero',
    ],
    'non-SEPA' => [
      'SEPA'    => 'end_create_sepa',
      'non-SEPA'=> 'end_create_nonsepa',
      'existing'=> 'end_assign_existing',
      'None'    => 'end_create_zero',
    ],
    'existing' => [
      'SEPA'    => 'end_create_sepa',
      'non-SEPA'=> 'end_create_nonsepa',
      'existing'=> 'end_assign_existing',
      'None'    => 'end_create_zero',
    ],
    'None' => [
      'SEPA'    => 'endzero_create_sepa',
      'non-SEPA'=> 'endzero_create_nonsepa',
      'existing'=> 'endzero_assign_existing',
      'None'    => 'no_change',
    ],
  ];

  private static function getPaymentTransitionActions()
  {
    return [
      'terminate_create_sepa' => function ($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'terminate_create_nonsepa' => function ($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'terminate_assign_existing' => function ($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return null;
      },
      'terminate_create_zero' => function ($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types, 0);
      },
      'end_create_sepa' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'end_create_nonsepa' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'end_assign_existing' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return null;
      },
      'end_create_zero' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types, 0);
      },
      'endzero_create_sepa' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'endzero_create_nonsepa' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'endzero_assign_existing' => function ($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return null;
      },
      'no_change' => function ($self, $contract_before, $to, $types) {
        return null;
      }
    ];
  }

  public function execute(): void {
    $contract_before = $this->getContract(TRUE);
    $contract_update = [];

    $membership_type_update = $this->getParameter('contract_updates.ch_membership_type');

    if ($membership_type_update ) {
      $payment_types = CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);

      $from = $contract_before['membership_payment.payment_instrument'] ?? null;
      $to = $this->getParameter('contract_updates.ch_payment_instrument') ?? $from;

      $fromType = $this->classifyPaymentInstrument($from, $payment_types);
      $toType = $this->classifyPaymentInstrument($to, $payment_types);

      $transition = self::$paymentTransitions[$fromType][$toType] ?? 'no_change';

      $actions = self::getPaymentTransitionActions();

      $action = $actions[$transition] ?? $actions['no_change'];

      $new_payment_contract = $action($this, $contract_before, $to, $payment_types);

      if ($from !== $to) {
        $contract_update['membership_payment.payment_instrument'] = $to;
      }

      if ($new_payment_contract) {
        $contract_update['membership_payment.membership_recurring_contribution'] = $new_payment_contract;
      }

    } else {
      $new_payment_contract = CRM_Contract_SepaLogic::updateSepaMandate(
          $this->getContractID(),
          $contract_before,
          $this->data,
          $this->data,
          $this->getActionName()
        );
      if ($new_payment_contract) {
        $contract_update['membership_payment.membership_recurring_contribution'] = $new_payment_contract;
      }
    }

    $this->updateContract($contract_update);

    $contract_after = $this->getContract();
    foreach (CRM_Contract_Change::FIELD_MAPPING_CHANGE_CONTRACT as $membership_field => $change_field) {
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


  private function classifyPaymentInstrument($id, $payment_types): string {
    switch ($id) {
      case $payment_types['RCUR']:
        return 'SEPA';
      case $payment_types['Cash']:
      case $payment_types['EFT']:
        return 'non-SEPA';
      case isset($payment_types['None']) ? $payment_types['None'] : '9':
      case '9':
        return 'None';
      default:
        return 'other';
    }
  }

  private function terminateSepaMandate($contract) {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      CRM_Contract_SepaLogic::terminateSepaMandate($contract['membership_payment.membership_recurring_contribution']);
    }
  }

  private function endRecurringContribution($contract) {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      try {
        civicrm_api3('ContributionRecur', 'cancel', [
          'id' => $contract['membership_payment.membership_recurring_contribution'],
        ]);
      } catch (\Exception $e) {
        // Already cancelled or does not exist
      }
    }
  }

  private function endRecurringContributionZero($contract) {
    if (!empty($contract['membership_payment.membership_recurring_contribution'])) {
      try {
        civicrm_api3('ContributionRecur', 'cancel', [
          'id' => $contract['membership_payment.membership_recurring_contribution'],
          'amount' => 0,
        ]);
      } catch (\Exception $e) {
        // Already cancelled or does not exist
      }
    }
  }

  private function createSepaMandate($contract, $paymentInstrumentId, $payment_types) {
    return CRM_Contract_SepaLogic::updateSepaMandate(
      $this->getContractID(),
      $contract,
      $this->data,
      $this->data,
      $this->getActionName()
    );
  }

  private function createNonSepaRecurring($contract, $paymentInstrumentId, $payment_types, $amount = null) {
    $finalAmount = $amount !== null
      ? (float) $amount
      : ((float) $contract['membership_payment.membership_annual'] / (float) $contract['membership_payment.membership_frequency']);
    $cycleDay = isset($contract['membership_payment.cycle_day']) ? (int) $contract['membership_payment.cycle_day'] : 1;
    $accountHolder = $contract['membership_payment.from_name'] ?? 'Holder';
    $startDate = date('Y-m-d');
    $frequency = (int) $contract['membership_payment.membership_frequency'];
    $campaignId = $contract['campaign_id'] ?? null;

    return CRM_Contract_RecurringContribution::createRecurringContribution(
      (int) $contract['contact_id'],
      (string) $finalAmount,
      $startDate,
      $accountHolder,
      $paymentInstrumentId,
      $cycleDay,
      12 / $frequency,
      $campaignId
    );
  }

  private function assignExistingRecurringContribution($contract, $paymentInstrumentId) {

  }

  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
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
      ];
    }
  }

}
