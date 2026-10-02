<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
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

use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'OptionGroup_contract_payment_suspension_reason',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_payment_suspension_reason',
        'data_type' => 'String',
        'title' => E::ts('Contract: Payment Suspension Reason'),
        'description' => E::ts('Options for contract payment suspension reasons'),
        'is_reserved' => FALSE,
        'option_value_fields' => ['name', 'label', 'description'],
        'is_active' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_contract_payment_suspension_reason_OptionValue_Unknown',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'contract_payment_suspension_reason',
        'label' => E::ts('Unknown'),
        'value' => 'Unknown',
        'name' => 'Unknown',
        'is_active' => TRUE,
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
];
