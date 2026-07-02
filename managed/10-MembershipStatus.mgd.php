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
    'name' => 'MembershipStatus_New',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'New',
        'label' => E::ts('New'),
        'is_current_member' => TRUE,
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Current',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Current',
        'label' => E::ts('Current'),
        'is_current_member' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Grace',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Grace',
        'label' => E::ts('Grace'),
        'is_current_member' => TRUE,
        'is_active' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Pending',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Pending',
        'label' => E::ts('Pending'),
        'is_current_member' => TRUE,
        'is_reserved' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Cancelled',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Cancelled',
        'label' => E::ts('Cancelled'),
        'is_current_member' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Deceased',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Deceased',
        'label' => E::ts('Deceased'),
        'is_current_member' => FALSE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'MembershipStatus_Paused',
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Paused',
        'label' => E::ts('Paused'),
        'is_current_member' => TRUE,
      ],
      'match' => [
        'name',
      ],
    ],
  ],
];
