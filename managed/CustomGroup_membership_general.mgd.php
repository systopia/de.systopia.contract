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
    'name' => 'CustomGroup_membership_general',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_general',
        'table_name' => 'civicrm_value_membership_general',
        'title' => E::ts('General Information'),
        'extends' => 'Membership',
        'collapse_adv_display' => TRUE,
      ],
      'match' => ['name'],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_general_CustomField_membership_channel',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_channel',
        'column_name' => 'membership_channel',
        'label' => E::ts('Membership Channel'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'option_group_id.name' => 'contact_channel',
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_general_CustomField_membership_reference',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_reference',
        'column_name' => 'membership_reference',
        'label' => E::ts('Reference Number'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 24,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_general_CustomField_membership_contract',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_general',
        'name' => 'membership_contract',
        'column_name' => 'membership_contract',
        'label' => E::ts('Contract Number'),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'text_length' => 24,
        'in_selector' => TRUE,
      ],
      'match' => [
        'name',
        'custom_group_id',
      ],
    ],
  ],
];
