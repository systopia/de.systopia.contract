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
use Civi\Api4\Generic\BasicGetAction;
use Civi\Api4\Membership;

/**
 * "id" is required in the WHERE clause.
 */
class GetAction extends BasicGetAction {

  public function __construct() {
    parent::__construct(ContractRelatedMembership::getEntityName(), 'get');
  }

  /**
   * @phpstan-return list<array<string, mixed>>
   */
  public function getRecords(): array {
    foreach ($this->getWhere() as $where) {
      // "id" is required.
      if ('id' === $where[0]) {
        /** @phpstan-var list<array<string, mixed>> $membershipResult */
        $membershipResult = Membership::get($this->getCheckPermissions())
          ->addSelect('id', 'owner_membership_id', 'contact_id', 'start_date', 'end_date')
          ->addWhere(...$where)
          ->execute()
          ->getArrayCopy();
        foreach ($membershipResult as &$membership) {
          $membership['contract_id'] = $membership['owner_membership_id'];
          unset($membership['owner_membership_id']);
        }
        return $membershipResult;
      }
    }

    throw new \RuntimeException('No condition for "id" given.');
  }

}
