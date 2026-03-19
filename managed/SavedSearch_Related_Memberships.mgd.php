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
  [
    'name' => 'SavedSearch_Primary_and_Secondary_Memberships',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Primary_and_Secondary_Memberships',
        'label' => E::ts('Primary and Secondary Memberships'),
        'api_entity' => 'Membership',
        'api_params' => [
          'version' => 4,
          'select' => [
            'IFNULL(owner_membership_id, id) AS IFNULL_owner_membership_id_id',
            'id',
            'IFNULL(owner_membership_id.contact_id, contact_id) AS IFNULL_owner_membership_id_contact_id_contact_id',
            'is_primary_member',
            'contact_id.sort_name',
            'join_date',
            'start_date',
            'end_date',
            'status_id:label',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'Membership AS Membership_Membership_owner_membership_id_01',
              'LEFT',
              [
                'id',
                '=',
                'Membership_Membership_owner_membership_id_01.owner_membership_id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Primary_and_Secondary_Memberships_SearchDisplay_Primary_and_Secondary_Memberships_Table',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Primary_and_Secondary_Memberships_Table',
        'label' => E::ts('Primary and Secondary Memberships Table'),
        'saved_search_id.name' => 'Primary_and_Secondary_Memberships',
        'type' => 'table',
        'settings' => [
          'description' => E::ts('This display shows primary and associated secondary memberships.'),
          'sort' => [
            [
              'IFNULL_owner_membership_id_id',
              'ASC',
            ],
            [
              'is_primary_member',
              'DESC',
            ],
          ],
          'limit' => 50,
          'pager' => [],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'id',
              'label' => E::ts('Membership ID'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.sort_name',
              'label' => E::ts('Contact sort name'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'join_date',
              'label' => E::ts('Member since'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'label' => E::ts('Start date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'label' => E::ts('End date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'status_id:label',
              'label' => E::ts('Status'),
              'sortable' => TRUE,
            ],
            [
              'size' => 'btn-xs',
              'links' => [
                [
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('View'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'conditions' => [],
                ],
                [
                  'path' => 'civicrm/contract/modify?modify_action=update&id=[id]',
                  'icon' => 'fa-pen-to-square',
                  'text' => E::ts('Update'),
                  'style' => 'default',
                  'conditions' => [
                    [
                      'is_primary_member',
                      '=',
                      TRUE,
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => 'civicrm/contract/modify?modify_action=pause&id=[id]',
                  'icon' => 'fa-pause',
                  'text' => E::ts('Pause'),
                  'style' => 'default',
                  'conditions' => [
                    [
                      'is_primary_member',
                      '=',
                      TRUE,
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => 'civicrm/contract/modify?modify_action=cancel&id=[id]',
                  'icon' => 'fa-ban',
                  'text' => E::ts('End'),
                  'style' => 'default',
                  'conditions' => [
                    [
                      'is_primary_member',
                      '=',
                      TRUE,
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => 'civicrm/membership/related#?owner_membership_id=[id]',
                  'icon' => 'fa-children',
                  'text' => E::ts('Related Memberships'),
                  'style' => 'default',
                  'conditions' => [
                    [
                      'is_primary_member',
                      '=',
                      TRUE,
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'buttons',
              'alignment' => 'text-right',
              'nowrap' => TRUE,
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'cssRules' => [
            [
              'bg-primary',
              'is_primary_member',
              '=',
              TRUE,
            ],
          ],
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Related_Memberships',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Related_Memberships',
        'label' => E::ts('Related Memberships'),
        'api_entity' => 'Membership',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'contact_id.sort_name',
            'membership_type_id:label',
            'start_date',
            'end_date',
            'owner_membership_id',
          ],
          'orderBy' => [],
          'where' => [
            [
              'is_primary_member',
              '=',
              FALSE,
            ],
            [
              'status_id:name',
              '=',
              'Current',
            ],
          ],
          'groupBy' => [],
          'join' => [],
          'having' => [],
        ],
      ],
      'match' => [
        'name',
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Related_Memberships_SearchDisplay_Related_Memberships',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Related_Memberships',
        'label' => E::ts('Related Memberships'),
        'saved_search_id.name' => 'Related_Memberships',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [],
          'limit' => 50,
          'pager' => [],
          'placeholder' => 5,
          'columns' => [
            [
              'type' => 'field',
              'key' => 'id',
              'dataType' => 'Integer',
              'label' => E::ts('Membership ID'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.sort_name',
              'dataType' => 'String',
              'label' => E::ts('Contact'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'membership_type_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Membership type'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'dataType' => 'Date',
              'label' => E::ts('Start date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'dataType' => 'Date',
              'label' => E::ts('End date'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'owner_membership_id',
              'dataType' => 'Integer',
              'label' => E::ts('Parent membership'),
              'sortable' => TRUE,
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
          ],
          'toolbar' => [
            [
              'entity' => '',
              'text' => E::ts('Add related membership'),
              'icon' => 'fa-external-link',
              'target' => 'crm-popup',
              'action' => '',
              'style' => 'default',
              'join' => '',
              'path' => 'civicrm/membership/related/add#?owner_membership_id=[owner_membership_id]',
              'task' => '',
              'conditions' => [],
            ],
          ],
        ],
      ],
      'match' => [
        'saved_search_id',
        'name',
      ],
    ],
  ],
];
