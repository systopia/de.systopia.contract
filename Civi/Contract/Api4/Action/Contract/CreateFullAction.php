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
use Civi\Contract\PaymentContract;

class CreateFullAction extends BasicCreateAction {

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
    $params = [];

    // Add core membership fields.
    $params['contact_id'] = $item['contact_id'];
    $params['membership_type_id'] = $item['membership_type_id'];
    $params['start_date'] = self::formatDate($item['start_date']);
    $params['join_date'] = self::formatDate($item['join_date']);
    if (isset($item['end_date'])) {
      $params['end_date'] = self::formatDate($item['end_date']);
    }
    $params['campaign_id'] = $item['campaign_id'] ?? '';

    // Add custom fields.
    $params['membership_general.membership_reference'] = $item['membership_reference'] ?? '';
    $params['membership_general.membership_contract'] = $item['membership_contract'] ?? '';
    $params['membership_general.membership_channel'] = $item['membership_channel'] ?? '';

    // Add payment contract.
    $paymentContract = new PaymentContract($item);
    $params['membership_payment.membership_recurring_contribution'] = $paymentContract->processPaymentContractCreate();
    $params['membership_payment.from_name'] = $item['account_holder'] ?? '';

    // Add activity parameters.
    $params['note'] = $item['activity_details'] ?? '';
    $params['medium_id'] = $item['activity_medium'] ?? '';

    \CRM_Contract_CustomData::resolveCustomFields($params);
    // TODO: Implement with API4.
    $contractResult = civicrm_api3('Contract', 'create', $params);
    return reset($contractResult['values']);
  }

  private static function formatDate(?string $date): string {
    return \CRM_Utils_Date::processDate($date ?? '', NULL, FALSE, 'Y-m-d H:i:s');
  }

}
