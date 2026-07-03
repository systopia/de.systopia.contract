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
    'name' => 'CustomGroup_membership_cancellation',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_cancellation',
        'table_name' => 'civicrm_value_membership_cancellation',
        'title' => E::ts('Cancellation Information'),
        'extends' => 'Membership',
        'collapse_adv_display' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_cancellation_CustomField_membership_cancel_date',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_cancellation',
        'name' => 'membership_cancel_date',
        'column_name' => 'membership_cancel_date',
        'label' => E::ts('Cancellation Date'),
        'data_type' => 'Date',
        'html_type' => 'Select Date',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'date_format' => 'mm/dd/yy',
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_cancellation_CustomField_membership_cancel_reason',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_cancellation',
        'name' => 'membership_cancel_reason',
        'column_name' => 'membership_cancel_reason',
        'label' => E::ts('Cancel Reason'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'option_group_id.name' => 'contract_cancel_reason',
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
