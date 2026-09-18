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

namespace Civi\Contract\Change\Type;

use Civi\Contract\Change\AbstractContractChange;
use CRM_Contract_ExtensionUtil as E;

class AddRelatedMembershipChange extends AbstractContractChange {

  public static function getActivityTypeName(): string {
    return 'Secondary_Membership_Created';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-person-circle-plus';
  }

  public static function getTitle(): string {
    return E::ts('Create Secondary Membership');
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    return E::ts('New related membership');
  }

}
