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

namespace Civi\Contract\DataFixture;

use Civi\Api4\Contract;
use Civi\Api4\FinancialType;

final class ContractFixture {

  /**
   * @param int $contactId
   * @param int $membershipTypeId
   * @param array<string, mixed> $values
   *
   * @return array{id: int, ...}
   */
  public static function addSepaFixture(
    int $contactId,
    int $membershipTypeId,
    array $values = [],
    ?string $financialType = NULL
  ): array {
    $values['financial_type_id'] ??= FinancialType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', $financialType ?? 'Donation')
      ->execute()
      ->single()['id'];

    // @phpstan-ignore return.type
    return Contract::createfull(FALSE)
      ->setValues($values + [
        'contact_id' => $contactId,
        'membership_type_id' => $membershipTypeId,
        'join_date' => date('Y-m-d', strtotime('-7 days')),
        'start_date' => date('Y-m-d', strtotime('-7 days')),
        'end_date' => date('Y-m-d', strtotime('+1 year')),
        'iban' => 'DE12500105170648489890',
        'bic' => 'TESTDEFFXXX',
        'payment_amount' => 12.34,
        'payment_frequency' => 4,
        'cycle_day' => 12,
        'payment_option' => 'RCUR',
        'account_holder' => 'John Doe',
      ])
      ->execute()
      ->single();
  }

}
