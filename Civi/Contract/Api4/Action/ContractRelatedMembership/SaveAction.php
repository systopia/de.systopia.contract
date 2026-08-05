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

use Civi\Api4\Contract;
use Civi\Api4\ContractRelatedMembership;
use Civi\Api4\Generic\BasicSaveAction;

class SaveAction extends BasicSaveAction {

  public function __construct() {
    parent::__construct(ContractRelatedMembership::getEntityName(), 'save');
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-param array{
   *     id?: int,
   *     contract_id?: int,
   *     contact_id?: int,
   *     start_date?: string,
   *     end_date?: string,
   *   } $item
   *
   * @phpstan-return array<string, mixed>
   *
   * @phpstan-ignore method.childParameterType
   */
  public function writeRecord($item): array {
    if (isset($item['id'])) {
      if (array_key_exists('end_date', $item)) {
        return Contract::endRelatedMembership()
          ->setRelatedMembershipId($item['id'])
          ->setEndDate($item['end_date'])
          ->execute()
          ->getArrayCopy();
      }
    }
    else {
      /** @phpstan-var array{contract_id: int, contact_id: int} $item */
      return Contract::addRelatedMembership()
        ->setContractId($item['contract_id'])
        ->setContactId($item['contact_id'])
        ->setStartDate($item['start_date'] ?? NULL)
        ->execute()
        ->getArrayCopy();
    }

    return [];
  }

}
