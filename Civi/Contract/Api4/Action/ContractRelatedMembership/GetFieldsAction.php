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

namespace Civi\Contract\Api4\Action\ContractRelatedMembership;

use Civi\Api4\ContractRelatedMembership;
use Civi\Api4\Generic\BasicGetFieldsAction;
use CRM_Contract_ExtensionUtil as E;

class GetFieldsAction extends BasicGetFieldsAction {

  public function __construct() {
    parent::__construct(ContractRelatedMembership::getEntityName(), 'getFields');
  }

  /**
   * @phpstan-return list<array<string, array<string, scalar>|array<scalar>|scalar|null>>
   */
  protected function getRecords(): array {
    return [
      [
        'name' => 'id',
        'title' => E::ts('Membership ID'),
        'type' => 'Field',
        'nullable' => FALSE,
        'required' => in_array($this->getAction(), ['get', 'update'], TRUE),
        'data_type' => 'Integer',
        'readonly' => TRUE,
      ],
      [
        'name' => 'contract_id',
        'title' => E::ts('Contract ID'),
        'type' => 'Field',
        'nullable' => FALSE,
        'required' => $this->getAction() === 'create',
        'data_type' => 'Integer',
        'input_type' => 'Number',
        'input_attrs' => [],
      ],
      [
        'name' => 'contact_id',
        'title' => E::ts('Contact ID'),
        'type' => 'Field',
        'nullable' => FALSE,
        'required' => $this->getAction() === 'create',
        'data_type' => 'Integer',
        'fk_entity' => 'Contact',
        'fk_column' => 'id',
        'input_type' => 'EntityRef',
        'input_attrs' => [],
      ],
      [
        'name' => 'start_date',
        'title' => E::ts('Start Date'),
        'data_type' => 'Date',
        'input_type' => 'Date',
        'input_attrs' => [],
      ],
      [
        'name' => 'end_date',
        'title' => E::ts('End Date'),
        'data_type' => 'Date',
        'input_type' => 'Date',
        'input_attrs' => [],
      ],
    ];
  }

}
