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
use Civi\Api4\Generic\BasicCreateAction;
use Civi\Api4\Generic\Result;

class CreateFullAction extends BasicCreateAction {

  public const PAYMENT_OPTION_NONE = 'None';

  public const PAYMENT_OPTION_SELECT = 'select';

  public const PAYMENT_OPTION_NOCHANGE = 'nochange';

  public const PAYMENT_OPTION_SEPA = 'RCUR';

  public function __construct() {
    parent::__construct(Contract::getEntityName(), 'createFull');
  }

  protected function validateValues() {
    parent::validateValues();

    // TODO: Validate required parameters per payment option
  }

  /**
   * @inheritDoc
   */
  protected function writeRecord($item) {
    // Add core membership fields.
    $params['contact_id'] = $item['contact_id'];
    $params['membership_type_id'] = $item['membership_type_id'];
    $params['start_date'] = \CRM_Utils_Date::processDate($item['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s');
    $params['join_date'] = \CRM_Utils_Date::processDate($item['join_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s');
    if (isset($item['end_date'])) {
      $params['end_date'] = \CRM_Utils_Date::processDate($item['end_date'], NULL, FALSE, 'Y-m-d H:i:s');
    }
    $params['campaign_id'] = $item['campaign_id'] ?? '';

    // Add custom fields.
    $params['membership_general.membership_reference'] = $item['membership_reference'] ?? '';
    $params['membership_general.membership_contract'] = $item['membership_contract'] ?? '';
    $params['membership_general.membership_channel'] = $item['membership_channel'] ?? '';

    // Add payment contract.
    if (!isset($item['cycle_day']) || $item['cycle_day'] < 1 || $item['cycle_day'] > 30) {
      // invalid cycle day
      $item['cycle_day'] = \CRM_Contract_SepaLogic::nextCycleDay();
    }
    $params['membership_payment.membership_recurring_contribution'] = self::processPaymentContract($item);
    $params['membership_payment.from_name'] = $item['account_holder'] ?? '';

    // Add activity parameters.
    $params['note'] = $item['activity_details'] ?? '';
    $params['medium_id'] = $item['activity_medium'] ?? '';

    \CRM_Contract_CustomData::resolveCustomFields($params);
    $contractResult = civicrm_api3('Contract', 'create', $params);
    return reset($contractResult['values']);
  }

  private static function processPaymentContract(array $item): ?int {
    switch ($item['payment_option']) {
      case self::PAYMENT_OPTION_SEPA:
        $sepaMandate = \CRM_Contract_SepaLogic::createNewMandate(
          [
            'type' => 'RCUR',
            'contact_id' => $item['contact_id'],
            'amount' => \CRM_Contract_SepaLogic::formatMoney($item['payment_amount']),
            'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
            'start_date' => \CRM_Utils_Date::processDate($item['start_date'], NULL, FALSE, 'Y-m-d H:i:s'),
            'creation_date' => date('YmdHis'),
            'date' => \CRM_Utils_Date::processDate($item['start_date'], NULL, FALSE, 'Y-m-d H:i:s'),
            'validation_date' => date('YmdHis'),
            'iban' => $item['iban'],
            'bic' => $item['bic'],
            'account_holder' => $item['account_holder'],
            'campaign_id' => $item['campaign_id'] ?? NULL,
            'financial_type_id' => 2,
            'frequency_unit' => 'month',
            'cycle_day' => $item['cycle_day'],
            'frequency_interval' => (int) (12 / $item['payment_frequency']),
          ]
        );
        return (int) $sepaMandate['entity_id'];

      case self::PAYMENT_OPTION_NONE:
        $paymentContractParams = [
          'contact_id' => $item['contact_id'],
          'amount' => 0,
          'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
          'start_date' => \CRM_Utils_Date::processDate($item['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
          'create_date' => date('YmdHis'),
          'date' => \CRM_Utils_Date::processDate($item['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
          'validation_date' => date('YmdHis'),
          'account_holder' => $item['account_holder'],
          'campaign_id' => $item['campaign_id'] ?? '',
          'payment_instrument_id' => \CRM_Contract_Configuration::getPaymentInstrumentIdByName($item['payment_option']),
          'financial_type_id' => 2,
          'frequency_unit' => 'month',
          'cycle_day' => $item['cycle_day'],
          'frequency_interval' => 1,
          'checkPermissions' => TRUE,
        ];
        \CRM_Contract_CustomData::resolveCustomFields($paymentContractParams);
        // TODO: Use API4.
        $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', $paymentContractParams);
        return $newRecurringContribution['id'];

      case self::PAYMENT_OPTION_SELECT:
        return $item['recurring_contribution_id'];

      case self::PAYMENT_OPTION_NOCHANGE:
        return NULL;

      // CREATE NEW PAYMENT CONTRACT for the other non-SEPA payment options like Cash or EFT
      default:
        // new contract
        $paymentContractParams = [
          'contact_id' => $item['contact_id'],
          'amount' => \CRM_Contract_SepaLogic::formatMoney($item['payment_amount']),
          'currency' => \CRM_Contract_SepaLogic::getCreditor()->currency,
          'start_date' => \CRM_Utils_Date::processDate($item['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
          'create_date' => date('YmdHis'),
          'date' => \CRM_Utils_Date::processDate($item['start_date'] ?? '', NULL, FALSE, 'Y-m-d H:i:s'),
          'validation_date' => date('YmdHis'),
          'account_holder' => $item['account_holder'] ?? NULL,
          'campaign_id' => $item['campaign_id'] ?? '',
          'payment_instrument_id' => \CRM_Contract_Configuration::getPaymentInstrumentIdByName($item['payment_option']),
          // Membership Dues
          'financial_type_id' => 2,
          'frequency_unit' => 'month',
          'cycle_day' => $item['cycle_day'],
          'frequency_interval' => (int) (12 / $item['payment_frequency']),
          'checkPermissions' => TRUE,
        ];
        \CRM_Contract_CustomData::resolveCustomFields($paymentContractParams);
        $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', $paymentContractParams);
        return $newRecurringContribution['id'];
    }
  }

}
