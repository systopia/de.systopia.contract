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

final class PaymentReinstatedEvent extends AbstractContractChangeEvent {

  /**
   * @param array<string, mixed> $custom
   */
  public function __construct(
    int $membershipId,
    int $contactId,
    public readonly int $contributionRecurId,
    public readonly int $sepaMandateId,
    public readonly bool $collectOutstanding,
    public readonly string $notes,
    public readonly bool $replaceSepaMandate,
    public readonly ?string $iban,
    public readonly ?string $bic,
    public readonly ?string $accountHolder,
    public readonly ?float $paymentAmount,
    public readonly ?int $paymentFrequency,
    public readonly ?int $cycleDay,
    public readonly array $custom,
  ) {
    parent::__construct($membershipId, $contactId);
  }

}
