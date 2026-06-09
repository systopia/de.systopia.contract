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
    'name' => 'CustomGroup_membership_payment',
    'entity' => 'CustomGroup',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'membership_payment',
        'table_name' => 'civicrm_value_membership_payment',
        'title' => E::ts('Payment Information'),
        'extends' => 'Membership',
        'collapse_adv_display' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_membership_annual',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'membership_annual',
        'label' => E::ts('Annual Membership Contribution'),
        'data_type' => 'Money',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'is_search_range' => TRUE,
        'column_name' => 'membership_annual',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_membership_frequency',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'membership_frequency',
        'label' => E::ts('Payment Interval'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'column_name' => 'membership_frequency',
        'option_group_id.name' => 'payment_frequency',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_membership_recurring_contribution',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'membership_recurring_contribution',
        'label' => E::ts('Recurring contribution/mandate'),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'membership_recurring_contribution',
        'in_selector' => TRUE,
        'fk_entity' => 'ContributionRecur',
        'fk_entity_on_delete' => 'cascade',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_to_ba',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'to_ba',
        'label' => E::ts("Organisation's Bank Account"),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'to_ba',
        'in_selector' => TRUE,
        'fk_entity' => 'BankAccount',
        'fk_entity_on_delete' => 'cascade',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_from_name',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'from_name',
        'label' => E::ts("Donor's Account Name"),
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'from_name',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_from_ba',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'from_ba',
        'label' => E::ts("Donor's Bank Account"),
        'data_type' => 'EntityReference',
        'html_type' => 'Autocomplete-Select',
        'is_searchable' => TRUE,
        'column_name' => 'from_ba',
        'in_selector' => TRUE,
        'fk_entity' => 'BankAccount',
        'fk_entity_on_delete' => 'cascade',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_cycle_day',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'cycle_day',
        'label' => E::ts('Cycle day'),
        'data_type' => 'Int',
        'html_type' => 'Text',
        'is_searchable' => TRUE,
        'column_name' => 'cycle_day',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_payment_instrument',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'payment_instrument',
        'label' => E::ts('Payment method'),
        'html_type' => 'Select',
        'is_searchable' => TRUE,
        'column_name' => 'payment_instrument',
        'in_selector' => TRUE,
        'option_group_id.name' => 'payment_instrument',
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'CustomGroup_membership_payment_CustomField_defer_payment_start',
    'entity' => 'CustomField',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'custom_group_id.name' => 'membership_payment',
        'name' => 'defer_payment_start',
        'label' => E::ts('Defer Payment Start'),
        'data_type' => 'Boolean',
        'html_type' => 'Radio',
        'default_value' => '1',
        'is_searchable' => TRUE,
        'column_name' => 'defer_payment_start',
        'in_selector' => TRUE,
      ],
      'match' => [
        'custom_group_id',
        'name',
      ],
    ],
  ],
];
