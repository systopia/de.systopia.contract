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

use CRM_Contract_ExtensionUtil as E;
use Civi\Api4\Contract;
use Civi\Api4\Generic\BasicGetFieldsAction;

class GetFieldsAction extends BasicGetFieldsAction {

  public function __construct() {
    parent::__construct(Contract::getEntityName(), 'getFields');
  }

  /**
   * @phpstan-return list<array<string, array<string, scalar>|array<scalar>|scalar|null>>
   */
  protected function getRecords(): array {
    return [
      [
        'name' => 'contact_id',
        'title' => E::ts('Contact ID'),
        'required' => TRUE,
        'data_type' => 'Integer',
      ],
      [
        'name' => 'membership_type_id',
        'title' => E::ts('Membership Type ID'),
        'required' => TRUE,
        'data_type' => 'Integer',
        // TODO: Add options.
      ],
      [
        'name' => 'start_date',
        'title' => E::ts('Start Date'),
        'nullable' => FALSE,
        'data_type' => 'Date',
      ],
      [
        'name' => 'payment_start_date',
        'title' => E::ts('Payment Start Date'),
        'data_type' => 'Date',
      ],
      [
        'name' => 'join_date',
        'title' => E::ts('Join Date'),
        'data_type' => 'Date',
      ],
      [
        'name' => 'end_date',
        'title' => E::ts('End Date'),
        'data_type' => 'Date',
      ],
      [
        'name' => 'membership_reference',
        'title' => E::ts('Membership Reference'),
        'data_type' => 'String',
      ],
      [
        'name' => 'membership_contract',
        'title' => E::ts('Membership Contract'),
        'data_type' => 'String',
      ],
      [
        'name' => 'membership_channel',
        'title' => E::ts('Membership Channel'),
        'data_type' => 'String',
      ],
      [
        'name' => 'payment_option',
        'title' => E::ts('Payment Option'),
        'data_type' => 'String',
        'options' => \CRM_Contract_Configuration::getPaymentOptions(TRUE, FALSE),
        'required' => TRUE,
      ],
      [
        'name' => 'recurring_contribution_id',
        'title' => E::ts('Recurring Contribution ID'),
        'data_type' => 'Integer',
      ],
      [
        'name' => 'payment_amount',
        'title' => E::ts('Payment Amount'),
        'data_type' => 'Money',
      ],
      [
        'name' => 'cycle_day',
        'title' => E::ts('Cycle Day'),
        'data_type' => 'Integer',
      ],
      [
        'name' => 'payment_frequency',
        'title' => E::ts('Payment Frequency'),
        'data_type' => 'Integer',
      ],
      [
        'name' => 'iban',
        'title' => E::ts('IBAN'),
        'data_type' => 'String',
      ],
      [
        'name' => 'bic',
        'title' => E::ts('BIC'),
        'data_type' => 'String',
      ],
      [
        'name' => 'account_holder',
        'title' => E::ts('Account Holder'),
        'data_type' => 'String',
      ],
      [
        'name' => 'campaign_id',
        'title' => E::ts('Campaign ID'),
        'data_type' => 'Integer',
      ],
      [
        'name' => 'activity_details',
        'title' => E::ts('Activity Details'),
        'data_type' => 'String',
      ],
      [
        'name' => 'activity_medium',
        'title' => E::ts('Activity Medium'),
        'data_type' => 'String',
      ],
    ];
  }

}
