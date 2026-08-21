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

interface SchedulableContractChangeInterface extends ContractChangeInterface {

  /**
   * Get the internal action name
   */
  public static function getActionName(): string;

  /**
   * Apply the given change to the contract
   *
   * @throws \Exception should anything go wrong in the execution
   */
  public function execute(): void;

  public function checkForConflicts(): void;

  /**
   * Get a list of required fields for this type
   *
   * @return list<string>
   */
  public function getRequiredFields(): array;

  /**
   * Check whether this change activity should actually be created
   *
   * @throws \Exception if the creation should be disallowed
   */
  public function shouldBeAccepted(): void;

  /**
   * Make sure that the data for this change is valid
   *
   * @throws \Exception if the data is not valid
   */
  public function verifyData(): void;

  /**
   * @throws \Exception if status change is not possible.
   */
  public function verifyStatusChange(): void;

  /**
   * @return list<string>
   *   Membership status names that this change can be applied to.
   */
  public static function getStartStatusList(): array;

}
