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
    'name' => 'CustomGroup_contract_activity',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_activity',
        'table_name' => 'civicrm_value_contract_activity',
        'title' => E::ts('Contract Activity'),
        'extends' => 'Activity',
        'extends_entity_column_value:name' => [
          'Contract_Signed',
          'Contract_Paused',
          'Contract_Resumed',
          'Contract_Updated',
          'Contract_Cancelled',
          'Contract_Revived',
        ],
        'style' => 'Inline',
        'collapse_display' => FALSE,
        'help_pre' => '',
        'help_post' => '',
        'weight' => 1,
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'collapse_adv_display' => TRUE,
        'is_reserved' => FALSE,
        'is_public' => FALSE,
        'icon' => '',
      ],
    ],
  ],
  [
    'name' => 'CustomField_contract_activity.contract_id',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_activity',
        'name' => 'contract_id',
        'label' => E::ts('Contract'),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'is_reserved' => FALSE,
        'is_required' => TRUE,
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'column_name' => 'contract_id',
        'in_selector' => FALSE,
        'fk_entity' => 'Membership',
        'fk_entity_on_delete' => 'cascade',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];
