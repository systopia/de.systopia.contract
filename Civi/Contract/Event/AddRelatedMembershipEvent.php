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

namespace Civi\Contract\Event;

use Civi\Contract\Contract;
use Symfony\Contracts\EventDispatcher\Event;

class AddRelatedMembershipEvent extends Event {

  protected Contract $primaryMembershipContract;

  protected int $relatedMembershipContactId;

  public function __construct(Contract $primaryMembershipContract, int $relatedMembershipContactId) {
    $this->primaryMembershipContract = $primaryMembershipContract;
    $this->relatedMembershipContactId = $relatedMembershipContactId;
  }

  public function getPrimaryMembershipContract(): Contract {
    return $this->primaryMembershipContract;
  }

  public function getRelatedMembershipContactId(): int {
    return $this->relatedMembershipContactId;
  }

}
