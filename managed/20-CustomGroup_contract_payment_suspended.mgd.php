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

use Civi\Contract\Change\ContractChangeTypeContainer;
use Civi\Contract\Change\Type\PaymentSuspendedChange;
use CRM_Contract_ExtensionUtil as E;

return [
  [
    'name' => 'CustomGroup_contract_payment_suspended',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'contract_payment_suspended',
        'table_name' => 'civicrm_value_contract_payment_suspended',
        'title' => E::ts('Contract: Payment Suspended'),
        'extends' => 'Activity',
        'extends_entity_column_value:name' => [
          PaymentSuspendedChange::getActivityTypeName(),
        ],
        'style' => 'Inline',
        'collapse_display' => FALSE,
        'help_pre' => '',
        'help_post' => '',
        'weight' => 1,
        'is_active' => TRUE,
        'is_multiple' => FALSE,
        'collapse_adv_display' => TRUE,
        'is_reserved' => TRUE,
        'is_public' => FALSE,
        'icon' => '',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_payment_suspended_CustomField_contribution_recur_id',
    'entity' => 'CustomField',
    'cleanup' => 'never',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_payment_suspended',
        'name' => 'contribution_recur_id',
        'label' => E::ts('Recurring Contribution'),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'is_reserved' => FALSE,
        'is_required' => TRUE,
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'column_name' => 'contribution_recur_id',
        'in_selector' => FALSE,
        'fk_entity' => 'ContributionRecur',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_contract_payment_suspended_CustomField_reason',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'contract_payment_suspended',
        'name' => 'reason',
        'column_name' => 'reason',
        'label' => E::ts('Payment Suspension Reason'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'option_group_id.name' => 'contract_payment_suspension_reason',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];
