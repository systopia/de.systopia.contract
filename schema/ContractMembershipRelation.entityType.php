<?php
/*
 * Copyright (C) 2025 SYSTOPIA GmbH
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
  'name' => 'ContractMembershipRelation',
  'table' => 'civicrm_contract_membership_relation',
  'class' => 'CRM_Contract_DAO_ContractMembershipRelation',
  'getInfo' => fn() => [
    'title' => E::ts('Related Membership Definition'),
    'title_plural' => E::ts('Related Membership Definitions'),
    'description' => E::ts(
      'Definitions for relations between membership types and relationship types for related membership contracts.'
    ),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique ContractMembershipRelation ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'membership_type_id' => [
      'title' => E::ts('Membership Type ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to MembershipType'),
      'entity_reference' => [
        'entity' => 'MembershipType',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'relationship_type_id' => [
      'title' => E::ts('Relationship Type ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'required' => TRUE,
      'description' => E::ts('FK to RelationshipType'),
      'entity_reference' => [
        'entity' => 'RelationshipType',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'relationship_direction' => [
      'title' => E::ts('Relationship Direction'),
      'sql_type' => 'varchar(3)',
      'input_type' => 'Text',
      'required' => TRUE,
      'description' => E::ts('Direction of relationship in which to derive membership (either "a_b" or "b_a").'),
    ],
    'related_membership_type_id' => [
      'title' => E::ts('Related membership type'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('Allowed type of related membership for this relation, NULL for any membership type.'),
      'entity_reference' => [
        'entity' => 'MembershipType',
        'key' => 'id',
        'on_delete' => 'SET NULL',
      ],
    ],
    'related_max_memberships' => [
      'title' => E::ts('Maximum Related Memberships'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Maximum allowed number of related memberships for this relation.'),
    ],
    'related_member_max_age' => [
      'title' => E::ts('Maximum Age of Related Members'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('Maximum allowed age of related members for this relation.'),
    ],
  ],
  'getIndices' => fn() => [],
  'getPaths' => fn() => [],
];
