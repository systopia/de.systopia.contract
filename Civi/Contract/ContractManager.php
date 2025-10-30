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

namespace Civi\Contract;

use Civi\Api4\Membership;

class ContractManager {

  /**
   * @phpstan-var array<int, \Civi\Contract\Contract>
   */
  private array $contracts = [];

  public function get(int $membershipId): Contract {
    if (!isset($this->contracts[$membershipId])) {
      $this->contracts[$membershipId] = new Contract($membershipId);
    }
    if (!isset($this->contracts[$membershipId])) {
      throw new \RuntimeException('Could not retrieve contract for membership with ID ' . $membershipId);
    }
    return $this->contracts[$membershipId];
  }

  public function getOwnerByRelated(int $relatedMembershipId): Contract {
    $relatedMembership = Membership::get(FALSE)
      ->addSelect('owner_membership_id')
      ->addWhere('id', '=', $relatedMembershipId)
      ->execute()
      ->single();
    if (!isset($relatedMembership['owner_membership_id'])) {
      throw new \RuntimeException('Membership with ID ' . $relatedMembershipId . ' is not a related membership');
    }
    return $this->get($relatedMembership['owner_membership_id']);
  }

  /**
   * @param int $membershipId
   *   The ID of the membership which to add a realted membership to.
   * @param int $contactId
   *   The ID of the contact which to add a related membership for.
   *
   * @return int
   *   The ID of the new related membership.
   */
  public function addRelatedMembership(int $membershipId, int $contactId): int {
    $contract = $this->get($membershipId);
    return $contract->addRelatedMembership($contactId);
  }

  public function endRelatedMembership(int $membershipId): void {
    $contract = $this->getOwnerByRelated($membershipId);
    $contract->endRelatedMembership($membershipId);
  }

}
