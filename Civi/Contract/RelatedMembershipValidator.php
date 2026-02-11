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

namespace Civi\Contract;

use Civi\Api4\ContractMembershipRelation;
use Civi\Api4\Relationship;

class RelatedMembershipValidator {

  public function validateContractMembershipRelations(
    Contract $primaryMembershipContract,
    int $relatedMembershipContactId
  ): bool {
    // TODO: Include inverse relationships.
    $relationships = Relationship::get()
      ->addWhere('contact_id_a', '=', $primaryMembershipContract->getMembership()['contact_id'])
      ->addWhere('contact_id_b', '=', $relatedMembershipContactId);

    // TODO: Retrieve ContractMembershipRelation entities for validation.
    $contractMembershipRelations = ContractMembershipRelation::get()
      ->addWhere('membership_type_id', '=', $primaryMembershipContract->getMembershipTypeId());

    return TRUE;
  }

}
