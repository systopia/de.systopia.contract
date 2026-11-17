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

namespace Civi\Contract\Event;

use Civi\Contract\Change\ContractChangeInterface;
use Civi\Contract\Change\SchedulableContractChangeInterface;

/**
 * This event is dispatched after contract changes. Two event names are used:
 * The FQCN and to observe only specific actions
 * ({@link self::getActionEventName()}).
 *
 * @see \Civi\Contract\Change\AbstractContractChange::updateContract()
 */
final class ContractChangedEvent extends AbstractContractChangedEvent {

  public static function getActionEventName(string $actionName): string {
    return self::class . '::' . $actionName;
  }

  /**
   * @param array{id: int, contact_id: int, ...} $contractBefore
   */
  public function __construct(
    private readonly array $contractBefore,
    private readonly ContractChangeInterface $contractChange,
  ) {
    parent::__construct($contractBefore['id'], $contractBefore['contact_id']);
  }

  public function getActionName(): ?string {
    return $this->contractChange instanceof SchedulableContractChangeInterface
      ? $this->contractChange::getActionName() : NULL;
  }

  public function getActivityTypeName(): string {
    return $this->contractChange::getActivityTypeName();
  }

  /**
   * @return array<string, mixed>
   */
  public function getContractBefore(): array {
    return $this->contractBefore;
  }

  /**
   * @return array<string, mixed>
   */
  public function getContractAfter(): array {
    return $this->contractChange->getContract();
  }

}
