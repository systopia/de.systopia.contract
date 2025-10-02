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

namespace Civi\Contract;

class PaymentContract {

  private const PAYMENT_OPTION_NONE = 'None';

  private const PAYMENT_OPTION_SELECT = 'select';

  private const PAYMENT_OPTION_NOCHANGE = 'nochange';

  private const PAYMENT_OPTION_SEPA = 'RCUR';

  private array $params;

  public function __construct(array $params) {
    $this->params = $params;
  }

  public function processPaymentContractCreate(): ?int {
    if (!isset($this->params['cycle_day']) || $this->params['cycle_day'] < 1 || $this->params['cycle_day'] > 30) {
      // invalid cycle day
      $this->params['cycle_day'] = \CRM_Contract_SepaLogic::nextCycleDay();
    }

    switch ($this->params['payment_option']) {
      case self::PAYMENT_OPTION_SEPA:
        return $this->createSepa();

      case self::PAYMENT_OPTION_NONE:
        return $this->createNoPayment();

      case self::PAYMENT_OPTION_SELECT:
        return $this->params['recurring_contribution_id'];

      case self::PAYMENT_OPTION_NOCHANGE:
        return NULL;

      default:
        return $this->createDefault();
    }
  }

  private function createSepa(): int {
    $sepaMandate = \CRM_Contract_SepaLogic::createNewMandate(
      [
        'type' => 'RCUR',
        'contact_id' => $this->params['contact_id'],
        'amount' => \CRM_Contract_SepaLogic::formatMoney($this->params['payment_amount']),
        'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
        'start_date' => \CRM_Utils_Date::processDate($this->params['start_date'], NULL, FALSE, 'Y-m-d H:i:s'),
        'creation_date' => date('YmdHis'),
        'date' => \CRM_Utils_Date::processDate($this->params['start_date'], NULL, FALSE, 'Y-m-d H:i:s'),
        'validation_date' => date('YmdHis'),
        'iban' => $this->params['iban'],
        'bic' => $this->params['bic'],
        'account_holder' => $this->params['account_holder'],
        'campaign_id' => $this->params['campaign_id'] ?? NULL,
        'financial_type_id' => 2,
        'frequency_unit' => 'month',
        'cycle_day' => $this->params['cycle_day'],
        'frequency_interval' => (int) (12 / $this->params['payment_frequency']),
      ]
    );
    return (int) $sepaMandate['entity_id'];
  }

  private function createNoPayment(): int {
    $paymentContractParams = [
      'contact_id' => $this->params['contact_id'],
      'amount' => 0,
      'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
      'start_date' => \CRM_Utils_Date::processDate($this->params['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
      'create_date' => date('YmdHis'),
      'date' => \CRM_Utils_Date::processDate($this->params['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
      'validation_date' => date('YmdHis'),
      'account_holder' => $this->params['account_holder'],
      'campaign_id' => $this->params['campaign_id'] ?? '',
      'payment_instrument_id' => \CRM_Contract_Configuration::getPaymentInstrumentIdByName(
        $this->params['payment_option']
      ),
      'financial_type_id' => 2,
      'frequency_unit' => 'month',
      'cycle_day' => $this->params['cycle_day'],
      'frequency_interval' => 1,
      'checkPermissions' => TRUE,
    ];
    \CRM_Contract_CustomData::resolveCustomFields($paymentContractParams);
    // TODO: Use API4.
    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', $paymentContractParams);
    return $newRecurringContribution['id'];
  }

  /**
   * Create new payment contract for the other non-SEPA payment options like Cash or EFT.
   */
  private function createDefault(): int {
    $paymentContractParams = [
      'contact_id' => $this->params['contact_id'],
      'amount' => \CRM_Contract_SepaLogic::formatMoney($this->params['payment_amount']),
      'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
      'start_date' => \CRM_Utils_Date::processDate($this->params['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
      'create_date' => date('YmdHis'),
      'date' => \CRM_Utils_Date::processDate($this->params['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
      'validation_date' => date('YmdHis'),
      'account_holder' => $this->params['account_holder'] ?? NULL,
      'campaign_id' => $this->params['campaign_id'] ?? '',
      'payment_instrument_id' => \CRM_Contract_Configuration::getPaymentInstrumentIdByName(
        $this->params['payment_option']
      ),
      // Membership Dues
      'financial_type_id' => 2,
      'frequency_unit' => 'month',
      'cycle_day' => $this->params['cycle_day'],
      'frequency_interval' => (int) (12 / $this->params['payment_frequency']),
      'checkPermissions' => TRUE,
    ];
    \CRM_Contract_CustomData::resolveCustomFields($paymentContractParams);
    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', $paymentContractParams);
    return $newRecurringContribution['id'];
  }

}
