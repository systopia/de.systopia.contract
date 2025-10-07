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
    'name' => 'SavedSearch_Primary_memberships',
    'entity' => 'SavedSearch',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Primary_memberships',
        'label' => E::ts('Primary Memberships'),
        'api_entity' => 'Membership',
        'api_params' => [
          'version' => 4,
          'select' => [
            'id',
            'contact_id.sort_name',
            'membership_type_id:label',
            'start_date',
            'end_date',
          ],
          'orderBy' => [],
          'where' => [
            [
              'is_primary_member',
              '=',
              TRUE,
            ],
            [
              'status_id:name',
              '=',
              'Current',
            ],
          ],
          'groupBy' => [
            'id',
          ],
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
    'name' => 'SavedSearch_Primary_memberships_SearchDisplay_Primary_memberships',
    'entity' => 'SearchDisplay',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Primary_memberships',
        'label' => E::ts('Primary Memberships'),
        'saved_search_id.name' => 'Primary_memberships',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            [
              'id',
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
              'dataType' => 'Integer',
              'label' => E::ts('Mitgliedschafts ID'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'contact_id.sort_name',
              'dataType' => 'String',
              'label' => E::ts('Kontakt Sortiername'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'membership_type_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Mitgliedsart'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'start_date',
              'dataType' => 'Date',
              'label' => E::ts('Beginn der Mitgliedschaft'),
              'sortable' => TRUE,
            ],
            [
              'type' => 'field',
              'key' => 'end_date',
              'dataType' => 'Date',
              'label' => E::ts('Ablaufdatum der Mitgliedschaft'),
              'sortable' => TRUE,
            ],
            [
              'links' => [
                [
                  'entity' => 'Membership',
                  'action' => 'view',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Zeige Mitgliedschaft'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'conditions' => [],
                ],
                [
                  'entity' => 'Membership',
                  'action' => 'update',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-pencil',
                  'text' => E::ts('Update Mitgliedschaft'),
                  'style' => 'default',
                  'path' => '',
                  'task' => '',
                  'conditions' => [],
                ],
                [
                  'path' => 'civicrm/membership/related#?owner_membership_id=[id]',
                  'icon' => 'fa-external-link',
                  'text' => E::ts('Related Memberships'),
                  'style' => 'default',
                  'conditions' => [],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'links',
              'alignment' => 'text-right',
            ],
            [
              'size' => 'btn-xs',
              'links' => [
                [
                  'entity' => 'Membership',
                  'action' => 'delete',
                  'join' => '',
                  'target' => 'crm-popup',
                  'icon' => 'fa-trash',
                  'text' => E::ts('Lösche Mitgliedschaft'),
                  'style' => 'danger',
                  'path' => '',
                  'task' => '',
                  'conditions' => [],
                ],
              ],
              'type' => 'buttons',
              'alignment' => 'text-right',
            ],
          ],
          'actions' => FALSE,
          'classes' => [
            'table',
            'table-striped',
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
