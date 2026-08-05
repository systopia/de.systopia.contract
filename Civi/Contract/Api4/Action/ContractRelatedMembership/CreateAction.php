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
use Civi\Api4\Generic\BasicCreateAction;

class CreateAction extends BasicCreateAction {

  public function __construct() {
    parent::__construct(ContractRelatedMembership::getEntityName(), 'create');
  }

  /**
   * {@inheritDoc}
   *
   * @phpstan-param array{
   *   contract_id: int,
   *   contact_id: int,
   *   start_date?: string,
   * } $item
   *
   * @phpstan-return array<string, mixed>
   *
   * @phpstan-ignore method.childParameterType
   */
  protected function writeRecord($item): array {
    return Contract::addRelatedMembership()
      ->setContractId($item['contract_id'])
      ->setContactId($item['contact_id'])
      ->setStartDate($item['start_date'] ?? NULL)
      ->execute()
      ->getArrayCopy();
  }

}
