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
      'SEPA'     => 'sepa_to_sepa_replace',
      'non-SEPA' => 'sepa_to_nonsepa_switch',
      'existing' => 'sepa_to_existing_reassign',
      'None'     => 'sepa_to_none_zero',
    ],
    'non-SEPA' => [
      'SEPA'     => 'nonsepa_to_sepa_switch',
      'non-SEPA' => 'nonsepa_to_nonsepa_replace',
      'existing' => 'nonsepa_to_existing_reassign',
      'None'     => 'nonsepa_to_none_zero',
    ],
    'existing' => [
      'SEPA'     => 'existing_to_sepa_switch',
      'non-SEPA' => 'existing_to_nonsepa_switch',
      'existing' => 'existing_to_existing_reassign',
      'None'     => 'existing_to_none_zero',
    ],
    'None' => [
      'SEPA'     => 'none_to_sepa_enable',
      'non-SEPA' => 'none_to_nonsepa_enable',
      'existing' => 'none_to_existing_reassign',
      'None'     => 'none_to_none_noop',
    ],
  ];

  private static function getPaymentTransitionActions() {
    return [
      'sepa_to_sepa_replace' => function($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'sepa_to_nonsepa_switch' => function($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'sepa_to_existing_reassign' => function($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return NULL;
      },
      'sepa_to_none_zero' => function($self, $contract_before, $to, $types) {
        $self->terminateSepaMandate($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types, 0);
      },

      'nonsepa_to_sepa_switch' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'nonsepa_to_nonsepa_replace' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'nonsepa_to_existing_reassign' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return NULL;
      },
      'nonsepa_to_none_zero' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types, 0);
      },

      'existing_to_sepa_switch' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'existing_to_nonsepa_switch' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'existing_to_existing_reassign' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return NULL;
      },
      'existing_to_none_zero' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContribution($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types, 0);
      },

      'none_to_sepa_enable' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        return $self->createSepaMandate($contract_before, $to, $types);
      },
      'none_to_nonsepa_enable' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        return $self->createNonSepaRecurring($contract_before, $to, $types);
      },
      'none_to_existing_reassign' => function($self, $contract_before, $to, $types) {
        $self->endRecurringContributionZero($contract_before);
        $self->assignExistingRecurringContribution($contract_before, $to);
        return NULL;
      },
      'none_to_none_noop' => function($self, $contract_before, $to, $types) {
        return NULL;
      },

      'no_change' => function($self, $contract_before, $to, $types) {
        return NULL;
      },
    ];
  }

// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function execute(): void {
// phpcs:enable
    $contract_before = $this->getContract(TRUE);
    $contract_update = [];

    $campaignId = $this->getParameter('contract_updates.ch_campaign_id')
      ?? ($this->data['campaign_id'] ?? NULL);

    $beforeCampaign = $contract_before['campaign_id'] ?? NULL;

    if ($beforeCampaign !== $campaignId) {
      $contract_update['campaign_id'] = empty($campaignId) ? NULL : (int) $campaignId;
    }

    $membershipTypeId = $this->getParameter('membership_type_id');
    if (($contract_before['membership_type_id'] != $membershipTypeId)  && !empty($membershipTypeId)) {
      $contract_update['membership_type_id'] = (int) $membershipTypeId;
    }

    $from = $contract_before['membership_payment.payment_instrument'] ?? NULL;
    $hasExplicitPaymentInstrumentChange = array_key_exists('contract_updates.ch_payment_instrument', $this->data);
    $to = $hasExplicitPaymentInstrumentChange
      ? $this->getParameter('contract_updates.ch_payment_instrument')
      : $from;

    if ($hasExplicitPaymentInstrumentChange) {
      $payment_types = CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);
      $fromType = $this->classifyPaymentInstrument($from, $payment_types);
      $toType   = $this->classifyPaymentInstrument($to, $payment_types);

      $transition = self::$paymentTransitions[$fromType][$toType] ?? 'no_change';
      $actions    = self::getPaymentTransitionActions();
      $action     = $actions[$transition] ?? $actions['no_change'];

      $new_payment_contract = $action($this, $contract_before, $to, $payment_types);

      if ($from !== $to) {
        $contract_update['membership_payment.payment_instrument'] = $to;
      }
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
      }
      catch (\Exception $e) {
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
      }
      catch (\Exception $e) {
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

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
  private function createNonSepaRecurring($contract, $paymentInstrumentIdOrName, $payment_types, $amount = NULL) {
  // phpcs:enable
    $instrumentName = is_numeric($paymentInstrumentIdOrName)
      ? array_search((string) $paymentInstrumentIdOrName, $payment_types, TRUE)
      : (string) $paymentInstrumentIdOrName;
    if ($instrumentName === FALSE) {
      $instrumentName = (string) $paymentInstrumentIdOrName;
    }

    $freq = (int) (
      $this->getParameter('contract_updates.ch_frequency')
      ?? $this->data['membership_payment.membership_frequency']
      ?? $contract['membership_payment.membership_frequency']
      ?? 12
    );
    $freq = $freq > 0 ? $freq : 12;

    if ($amount === NULL) {
      $annual = $this->getParameter('contract_updates.ch_annual')
        ?? $this->data['membership_payment.membership_annual']
        ?? $contract['membership_payment.membership_annual']
        ?? 0;
      $annual = CRM_Contract_SepaLogic::formatMoney($annual);
      $amount = $freq ? (float) $annual / $freq : 0;
    }
    $amount = CRM_Contract_SepaLogic::formatMoney($amount);

    $cycleDay = (int) (
      $this->getParameter('contract_updates.ch_cycle_day')
      ?? $this->data['membership_payment.cycle_day']
      ?? $contract['membership_payment.cycle_day']
      ?? 1
    );
    if ($cycleDay < 1 || $cycleDay > 30) {
      $cycleDay = CRM_Contract_SepaLogic::nextCycleDay();
    }

    $accountHolder = $this->getParameter('contract_updates.ch_from_name')
      ?? $this->data['membership_payment.from_name']
      ?? $contract['membership_payment.from_name']
      ?? 'Holder';

    $campaignId = $this->getParameter('contract_updates.ch_campaign_id')
      ?? $this->data['campaign_id']
      ?? $contract['campaign_id']
      ?? NULL;

    $interval = $freq ? 12 / $freq : 0;

    return CRM_Contract_RecurringContribution::createRecurringContribution(
      (int) $contract['contact_id'],
      (string) $amount,
      date('Y-m-d'),
      $accountHolder,
      $instrumentName,
      $cycleDay,
      $interval,
      $campaignId
    );
  }

  private function assignExistingRecurringContribution($contract_before, $to) {
    $rcId = (int) $this->getParameter('contract_updates.ch_recurring_contribution');
    if (!$rcId) {
      return NULL;
    }

    $rc = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $rcId,
      'return' => [
        'amount',
        'frequency_unit',
        'frequency_interval',
        'cycle_day',
        'payment_instrument_id',
      ],
    ]);

    $interval = max(1, (int) ($rc['frequency_interval'] ?? 1));
    $unit = (string) ($rc['frequency_unit'] ?? 'month');
    $freq = ($unit === 'month') ? (int) (12 / $interval) : (int) (1 / $interval);

    $annual = CRM_Contract_SepaLogic::formatMoney(((float) ($rc['amount'] ?? 0)) * $freq);

    $this->setParameter('contract_updates.ch_recurring_contribution', $rcId);
    $this->setParameter('contract_updates.ch_payment_instrument', (int) ($rc['payment_instrument_id'] ?? 0));
    $this->setParameter('contract_updates.ch_annual', $annual);
    $this->setParameter('contract_updates.ch_frequency', $freq);
    $this->setParameter('contract_updates.ch_cycle_day', (int) ($rc['cycle_day'] ?? 0));

    CRM_Contract_SepaLogic::setContractPaymentLink((int) $contract_before['id'], $rcId);

    return NULL;
  }

  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
      return E::ts('Update contract scheduled');
    }

    $before = (array) ($contract_before ?: []);
    $after  = (array) $contract_after;

    $map = [
      'membership_type_id'                      => E::ts('Type'),
      'membership_payment.membership_annual'    => E::ts('Annual amount'),
      'membership_payment.membership_frequency' => E::ts('Payment frequency'),
      'membership_payment.cycle_day'            => E::ts('Cycle day'),
      'membership_payment.payment_instrument'   => E::ts('Payment method'),
      'membership_payment.defer_payment_start'  => E::ts('Defer payment start'),
    ];

    $changes = [];
    foreach ($map as $field => $label) {
      $rawBefore = $before[$field] ?? NULL;
      $rawAfter  = $after[$field] ?? NULL;
      $valBefore = $this->labelValue($rawBefore, $field);
      $valAfter  = $this->labelValue($rawAfter, $field);
      if ($valBefore !== $valAfter) {
        if ($valBefore === '' || $valBefore === NULL) {
          $changes[] = "{$label}: {$valAfter}";
        }
        elseif ($valAfter === '' || $valAfter === NULL) {
          $changes[] = "{$label}: {$valBefore} → " . E::ts('none');
        }
        else {
          $changes[] = "{$label}: {$valBefore} → {$valAfter}";
        }
      }
    }

    if (!$changes) {
      return E::ts('Contract updated');
    }
    return E::ts('Contract updated') . ' — ' . implode('; ', $changes);
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
