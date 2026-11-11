<?php
/*
 * Copyright (C) 2025 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Contract\Api4\Action\Contract;

use Civi\Api4\Contract;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Generic\BasicUpdateAction;
use Civi\Api4\Membership;

class ModifyFullAction extends BasicUpdateAction {

  public function __construct() {
    parent::__construct(Contract::getEntityName(), 'modifyFull');
  }

  protected function validateValues(): void {
    parent::validateValues();

    // TODO: Validate required parameters per payment option
  }

  /**
   * @inheritDoc
   */
  // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded
  protected function writeRecord($item): array {
    $membership = Membership::get(FALSE)
      ->addSelect('contact_id')
      ->addWhere('id', '=', $item['id'])
      ->execute()
      ->single();

    $values = [
      'medium_id' => $item['medium_id'] ?? NULL,
      'note' => $item['note'],
    ];

    if (in_array($item['action'], ['update', 'revive'], TRUE)) {
      $types = \CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);

      // now add the payment
      switch ($item['payment_option']) {
        // select a new recurring contribution
        case 'select':
          $rcId = (int) $item['recurring_contribution'];
          $values['membership_payment.membership_recurring_contribution'] = $rcId;

          $rc = ContributionRecur::get(FALSE)
            ->addSelect(
              'amount',
              'frequency_unit',
              'frequency_interval',
              'cycle_day',
              'payment_instrument_id'
            )
            ->addWhere('id', '=', $rcId)
            ->execute()
            ->single();

          $freq = ($rc['frequency_unit'] === 'month')
            ? (int) (12 / max(1, (int) $rc['frequency_interval']))
            // In case it is a yearly frequency
            : (int) (1 / max(1, (int) $rc['frequency_interval']));

          $annual = \CRM_Contract_SepaLogic::formatMoney(
            ((float) $rc['amount']) * $freq
          );

          $values['membership_payment.membership_annual'] = $annual;
          $values['membership_payment.membership_frequency'] = $freq;
          $values['membership_payment.cycle_day'] = (int) ($rc['cycle_day'] ?? 0);
          $values['membership_payment.payment_instrument'] = (int) ($rc['payment_instrument_id'] ?? 0);
          break;

        case 'nochange':
          break;

        case 'None':
          $pi = $types['None'] ?? \CRM_Contract_Configuration::getPaymentInstrumentIdByName('None');
          if ($pi) {
            $values['membership_payment.payment_instrument'] = $pi;
            $values['contract_updates.ch_payment_instrument'] = $pi;
          }
          break;

        case 'modify':
        case 'RCUR':
          $amount = \CRM_Contract_SepaLogic::formatMoney($item['payment_amount']);
          $annual = \CRM_Contract_SepaLogic::formatMoney($item['payment_frequency'] * $amount);
          $from_ba = \CRM_Contract_BankingLogic::getOrCreateBankAccount(
            $membership['contact_id'],
            $item['iban'],
            isset($item['bic']) && '' !== $item['bic'] ? $item['bic'] : 'NOTPROVIDED'
          );
          $pi = $types['RCUR'] ?? \CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR');
          if ($pi) {
            $values['contract_updates.ch_payment_instrument'] = $pi;
          }
          $values['contract_updates.ch_annual'] = $annual;
          $values['contract_updates.ch_frequency'] = $item['payment_frequency'];
          $values['contract_updates.ch_cycle_day'] = $item['cycle_day'];
          $values['contract_updates.ch_from_ba'] = $from_ba;
          $values['contract_updates.ch_from_name'] = $item['account_holder'];
          $values['contract_updates.ch_defer_payment_start']
            = isset($item['defer_payment_start']) && '' !== $item['defer_payment_start'] ? '1' : '0';
          break;

        default:
          // a new payment option is picked
          $new_payment_option = $item['payment_option'];
          $payment_instrument_id = $types[$new_payment_option] ?? '';
          if ($payment_instrument_id) {
            $values['membership_payment.payment_instrument'] = $payment_instrument_id;
            $values['contract_updates.ch_payment_instrument'] = $payment_instrument_id;
          }

          // compile other change data
          $values['membership_payment.membership_annual'] = \CRM_Contract_SepaLogic::formatMoney(
            $item['payment_frequency'] * \CRM_Contract_SepaLogic::formatMoney($item['payment_amount'])
          );
          $values['membership_payment.membership_frequency'] = $item['payment_frequency'];
          $values['membership_payment.cycle_day'] = $item['cycle_day'];
          $values['membership_payment.to_ba'] = \CRM_Contract_BankingLogic::getCreditorBankAccount();
          $values['membership_payment.from_ba'] = \CRM_Contract_BankingLogic::getOrCreateBankAccount(
            $membership['contact_id'],
            $item['iban'],
            $item['bic']
          );
          $values['membership_payment.from_name'] = $item['account_holder'];
          $values['membership_payment.defer_payment_start']
            = isset($item['defer_payment_start']) && '' !== $item['defer_payment_start'] ? '1' : '0';
          break;
      }

      // add other changes
      $values['membership_type_id'] = $item['membership_type_id'];
      $values['campaign_id'] = $item['campaign_id'];
    }
    // If this is a cancellation
    elseif ($item['action'] === 'cancel') {
      $values['membership_cancellation.membership_cancel_reason'] = $item['cancel_reason'];

    }
    // If this is a pause
    elseif ($item['action'] === 'pause') {
      $values['resume_date'] = \CRM_Utils_Date::processDate($item['resume_date'], NULL, FALSE, 'Y-m-d');
    }

    return Contract::modify($this->getCheckPermissions())
      ->setId($item['id'])
      ->setAction($item['action'])
      ->setDate($item['date'] ?? NULL)
      ->setValues($values)
      ->execute()
      ->single();
  }

}
