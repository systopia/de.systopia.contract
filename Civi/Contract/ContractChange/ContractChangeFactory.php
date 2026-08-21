<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Contract\ContractChange;

/**
 * @phpstan-import-type changeT from \CRM_Contract_Change
 */
final class ContractChangeFactory {

  public static function getInstance(): self {
    /** @var self */
    return \Civi::service(self::class);
  }

  public function __construct(
    private readonly ContractChangeTypeContainer $typeContainer
  ) {}

  /**
   * @phpstan-param changeT $data
   *
   * @throws \CRM_Core_Exception
   * @throws \InvalidArgumentException
   *   If no activity type is given or the type is not a contract change type.
   */
  public function create(array $data): ContractChangeInterface {
    if (isset($data['activity_type_id:name'])) {
      $changeClass = $this->typeContainer->getClassForActivityType($data['activity_type_id:name']);
    }
    elseif (isset($data['activity_type_id'])) {
      $changeClass = $this->typeContainer->getClassForActivityTypeId($data['activity_type_id']);
    }
    else {
      throw new \InvalidArgumentException('No activity type given');
    }

    // make sure we're using the descriptive indices, not the custom_[id] ones
    \CRM_Contract_CustomData::labelCustomFields($data);

    // finally: create a change object on the data
    return new $changeClass($data);
  }

  /**
   * @phpstan-param changeT $data
   *
   * @throws \CRM_Core_Exception
   * @throws \InvalidArgumentException
   *   If no activity type is given or the type is not a schedulable contract
   *   change type.
   */
  public function createSchedulable(array $data): SchedulableContractChangeInterface {
    $change = $this->create($data);
    if (!$change instanceof SchedulableContractChangeInterface) {
      throw new \InvalidArgumentException('The given activity type is not a schedulable contract change type');
    }

    return $change;
  }

}
