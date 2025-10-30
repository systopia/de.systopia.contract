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

final class Contract {

  private int $membershipId;

  /**
   * @phpstan-var array<string, mixed>
   */
  private array $membership = [];

  public function __construct(int $membershipId) {
    $this->membershipId = $membershipId;

    try {
      $this->membership = Membership::get(FALSE)
        ->addWhere('id', '=', $this->membershipId)
        ->execute()
        ->single();
    }
    catch (\Exception $e) {
      throw new \RuntimeException(
        'Could not retrieve membership with ID ' . $this->membershipId,
        $e->getCode(),
        $e
      );
    }
  }

  public static function create(int $membershipId): Contract {
    return new static($membershipId);
  }

  /**
   * @phpstan-param array{id: int} $result
   */
  public static function createFromApiResult(array $result): Contract {
    $contract = new Contract($result['id']);
    $contract->membership = $result;
    return $contract;
  }

  public function addRelatedMembership(int $contactId): int {
    $relatedMembership = Membership::create(FALSE)
      ->addValue('owner_membership_id', $this->membershipId)
      ->addValue('contact_id', $contactId)
      ->addValue('membership_type_id', $this->membership['membership_type_id'])
      // TODO: Set more values?
      ->execute()
      ->single();
    return $relatedMembership['id'];
  }

  public function endRelatedMembership(int $relatedMembershipId): void {
    Membership::update(FALSE)
      ->addWhere('id', '=', $relatedMembershipId)
      ->addValue('status_id.name', 'Cancelled')
      ->execute();
  }

}
