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
    'name' => 'CustomGroup_contract_updates',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_updates',
        'table_name' => 'civicrm_value_contract_updates',
        'title' => E::ts('Contract Updates'),
        'extends' => 'Activity',
        'collapse_adv_display' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_membership_type',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_membership_type',
        'column_name' => 'ch_membership_type',
        'label' => E::ts('Membership Type'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_annual',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_annual',
        'column_name' => 'ch_annual',
        'label' => E::ts('Annual Membership Contribution'),
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_annual_diff',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_annual_diff',
        'column_name' => 'ch_annual_diff',
        'label' => E::ts('Increase'),
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_frequency',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_frequency',
        'column_name' => 'ch_frequency',
        'label' => E::ts('Payment Interval'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'option_group_id.name' => 'payment_frequency',
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_from_name',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_from_name',
        'column_name' => 'ch_from_name',
        'label' => E::ts("Donor's Account Name"),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_recurring_contribution',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_recurring_contribution',
        'column_name' => 'ch_recurring_contribution',
        'label' => E::ts('Payment Contract'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_to_ba',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_to_ba',
        'column_name' => 'ch_to_ba',
        'label' => E::ts("Organization's Bank Account"),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_from_ba',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_from_ba',
        'column_name' => 'ch_from_ba',
        'label' => E::ts("Member's Bank Account"),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_cycle_day',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_cycle_day',
        'column_name' => 'ch_cycle_day',
        'label' => E::ts('Cycle Day'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_payment_instrument',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_payment_instrument',
        'column_name' => 'ch_payment_instrument',
        'label' => E::ts('Payment Method'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_updates_CustomField_ch_defer_payment_start',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_updates',
        'name' => 'ch_defer_payment_start',
        'column_name' => 'ch_defer_payment_start',
        'label' => E::ts('Defer Payment Start'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => '1',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'is_view' => TRUE,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
