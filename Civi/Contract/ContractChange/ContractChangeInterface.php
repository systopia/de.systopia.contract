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

interface ContractChangeInterface {

  public static function getActivityTypeName(): string;

  public static function getActivityTypeIcon(): ?string;

  public static function getTitle(): string;

  /**
   * Calculate the subject line for this activity
   *
   * @phpstan-param array<string, mixed>|null $contractAfter
   *   Data of the contract after the change.
   * @phpstan-param array<string, mixed>|null $contractBefore
   *   Data of the contract before the change.
   */
  public function getSubject(?array $contractAfter, ?array $contractBefore = NULL): string;

  /**
   * Get the change ID
   */
  public function getID(): ?int;

  /**
   * Get the contract ID
   */
  public function getContractID(): int;

  /**
   * Derive/populate additional data
   */
  public function populateData(): void;

  /**
   * Get the contract data
   *
   * @return array<string, mixed> contract data
   */
  public function getContract(bool $withPaymentData = FALSE): array;

  /**
   * Update the contract with the given data
   *
   * @param array<string, mixed> $updates changes: attribute->value
   *
   * @throws \Exception
   */
  public function updateContract(array $updates): void;

  /**
   * Set a parameter with the activity.
   */
  public function setParameter(string $key, mixed $value): void;

  /**
   * Get a parameter from the activity
   */
  public function getParameter(string $key, mixed $default = NULL): mixed;

  /**
   * Save data to the DB (activity)
   */
  public function save(): void;

  /**
   * Set change status
   *
   * @param string $status valid activity status
   */
  public function setStatus(string $status): void;

}
