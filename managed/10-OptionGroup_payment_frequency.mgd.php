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
    'name' => 'OptionGroup_payment_frequency',
    'entity' => 'OptionGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'payment_frequency',
        'title' => E::ts('Payment Intervals'),
        'description' => E::ts('The value describes the payment interval in months.'),
        'is_reserved' => FALSE,
        'option_value_fields' => ['name', 'label', 'description'],
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_one-off',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('one-off'),
        'value' => '0',
        'name' => 'one-off',
      ],
      'match' => [
        'option_group_id',
        'name',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_annually',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('annually'),
        'value' => '1',
        'name' => 'annually',
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_semi-annually',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('semi-annually'),
        'value' => '2',
        'name' => 'semi-annually',
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_trimestral',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('trimestral'),
        'value' => '3',
        'name' => 'trimestral',
        'is_active' => FALSE,
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_quarterly',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('quarterly'),
        'value' => '4',
        'name' => 'quarterly',
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_bi-monthly',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('bi-monthly'),
        'value' => '6',
        'name' => 'bi-monthly',
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_payment_frequency_OptionValue_monthly',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'payment_frequency',
        'label' => E::ts('monthly'),
        'value' => '12',
        'name' => 'monthly',
      ],
      'match' => [
        'option_group_id',
        'value',
      ],
    ],
  ],
];
