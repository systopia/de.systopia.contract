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
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Signed',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Sign Contract'),
        'name' => 'Contract_Signed',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-dot-circle-o',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Paused',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Pause Contract'),
        'name' => 'Contract_Paused',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-pause-circle-o',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Resumed',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Resume Contract'),
        'name' => 'Contract_Resumed',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-play-circle-o',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Updated',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Update Contract'),
        'name' => 'Contract_Updated',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-arrow-circle-o-up',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Cancelled',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Cancel Contract'),
        'name' => 'Contract_Cancelled',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-stop-circle-o',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Contract_Revived',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Revive Contract'),
        'name' => 'Contract_Revived',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-play-circle-o',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Secondary_Membership_Created',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Create Secondary Membership'),
        'name' => 'Secondary_Membership_Created',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-person-circle-plus',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Secondary_Membership_Ended',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('End Secondary Membership'),
        'name' => 'Secondary_Membership_Ended',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-person-circle-minus',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'OptionGroup_activity_type_OptionValue_Secondary_Membership_Updated',
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => E::ts('Update Secondary Membership'),
        'name' => 'Secondary_Membership_Updated',
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => 'fa-person-circle-exclamation',
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ],
];
