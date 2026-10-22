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

use Civi\Contract\MembershipStatusEnum;

$membershipStatuses = [];
foreach (MembershipStatusEnum::cases() as $status) {
  $membershipStatuses[] = [
    'name' => 'MembershipStatus_' . $status->name,
    'entity' => 'MembershipStatus',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => $status->name,
        'label' => $status->label(),
        'is_current_member' => $status->isCurrentMember(),
        'is_active' => $status->isActive(),
        'is_reserved' => $status->isReserved(),
      ],
      'match' => [
        'name',
      ],
    ],
  ];
}

return $membershipStatuses;
