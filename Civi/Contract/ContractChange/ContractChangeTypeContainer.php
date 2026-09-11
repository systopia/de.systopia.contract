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

use Civi\Api4\OptionValue;

final class ContractChangeTypeContainer {

  /**
   * @var ?array<int, string>
   */
  private ?array $activityTypesById = NULL;

  /**
   * @var array<string, class-string<SchedulableContractChangeInterface>>
   */
  private array $classesByAction = [];

  /**
   * @var array<string, class-string<ContractChangeInterface>>
   */
  private array $classesByActivityType = [];

  public static function getInstance(): self {
    /** @var self */
    return \Civi::service(self::class);
  }

  /**
   * @param list<class-string<ContractChangeInterface>> $classes
   */
  public function __construct(array $classes) {
    foreach ($classes as $class) {
      if (is_a($class, SchedulableContractChangeInterface::class, TRUE)) {
        $this->classesByAction[$class::getActionName()] = $class;
      }
      $this->classesByActivityType[$class::getActivityTypeName()] = $class;
    }
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function getActivityTypeId(string $activityTypeName): int {
    return array_flip($this->getActivityTypesById())[$activityTypeName];
  }

  /**
   * @return list<int>
   *
   * @throws \CRM_Core_Exception
   */
  public function getActivityTypeIds(): array {
    return array_keys($this->getActivityTypesById());
  }

  /**
   * @return list<string>
   */
  public function getActivityTypes(): array {
    return array_keys($this->classesByActivityType);
  }

  /**
   * @return class-string<SchedulableContractChangeInterface>
   */
  public function getClassForAction(string $action): string {
    if (!isset($this->classesByAction[$action])) {
      throw new \InvalidArgumentException(
        "Action '$action' is not a valid contract change action."
      );
    }

    return $this->classesByAction[$action];
  }

  /**
   * @return class-string<ContractChangeInterface>
   *
   * @throws \CRM_Core_Exception
   * @throws \InvalidArgumentException
   *    If the given activity type is not a contract change type.
   */
  public function getClassForActivityTypeId(int $activityTypeId): string {
    $activityTypeName = $this->getActivityTypesById()[$activityTypeId] ?? NULL;
    if (NULL === $activityTypeName) {
      throw new \InvalidArgumentException(
        "Activity type ID '$activityTypeId' is not a valid contract change type."
      );
    }

    return $this->getClassForActivityType($activityTypeName);
  }

  /**
   * @return class-string<ContractChangeInterface>
   *
   * @throws \InvalidArgumentException
   *   If the given activity type is not a contract change type.
   */
  public function getClassForActivityType(string $activityTypeName): string {
    if (!isset($this->classesByActivityType[$activityTypeName])) {
      throw new \InvalidArgumentException(
        "Activity type name '$activityTypeName' is not a valid contract change type."
      );
    }

    return $this->classesByActivityType[$activityTypeName];
  }

  /**
   * @return array<string, class-string<\Civi\Contract\ContractChange\ContractChangeInterface>>
   */
  public function getClassesByActivityType(): array {
    return $this->classesByActivityType;
  }

  /**
   * @return array<int, string>
   *
   * @throws \CRM_Core_Exception
   */
  private function getActivityTypesById(): array {
    return $this->activityTypesById ??= OptionValue::get(FALSE)
      ->addSelect('value', 'name')
      ->addWhere('option_group_id.name', '=', 'activity_type')
      ->addWhere('name', 'IN', $this->getActivityTypes())
      ->execute()
      ->indexBy('value')
      ->column('name');
  }

}
