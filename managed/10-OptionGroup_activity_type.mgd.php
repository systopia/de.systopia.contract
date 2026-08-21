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

use Civi\Contract\ContractChange\ContractChangeTypeContainer;

$activityTypes = [];
foreach (ContractChangeTypeContainer::getInstance()->getClassesByActivityType() as $class) {
  $activityTypes[] = [
    'name' => 'OptionGroup_activity_type_OptionValue_' . $class::getActivityTypeName(),
    'entity' => 'OptionValue',
    'cleanup' => 'unused',
    'update' => 'unmodified',
    'params' => [
      'version' => 4,
      'values' => [
        'option_group_id.name' => 'activity_type',
        'label' => $class::getTitle(),
        'name' => $class::getActivityTypeName(),
        'filter' => 1,
        'is_reserved' => TRUE,
        'icon' => $class::getActivityTypeIcon(),
      ],
      'match' => [
        'option_group_id',
        'name',
      ],
    ],
  ];
}

return $activityTypes;
