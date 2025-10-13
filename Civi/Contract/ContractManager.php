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

class ContractManager {

  /**
   * @phpstan-var array<int, \Civi\Contract\Contract>
   */
  private array $contracts = [];

  public function get(int $membershipId): ?Contract {
    if (!isset($this->contracts[$membershipId])) {
      $this->contracts[$membershipId] = new Contract($membershipId);
    }
    return $this->contracts[$membershipId] ?? NULL;
  }

  public function addRelatedMembership(int $membershipId, int $contactId): int {
    $contract = $this->get($membershipId);
    if (NULL === $contract) {
      throw new \RuntimeException('Could not retrieve contract for membership with ID ' . $membershipId);
    }
    return $contract->addRelatedMembership($contactId);
  }

}
