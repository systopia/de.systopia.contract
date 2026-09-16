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

use Civi\Contract\Change\ActionMenuEntry;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Revive Membership" change
 */
class ContractChangeRevive extends AbstractContractChangeUpdate {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-refresh')
      ->setWeight(30);
  }

  public static function getActionName(): string {
    return 'revive';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Revived';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-play-circle-o';
  }

  public static function getStartStatusList(): array {
    return ['Cancelled'];
  }

  public static function getTitle(): string {
    return E::ts('Revive Contract');
  }

  /**
   * @inheritDoc
   */
  public function updateContract(array $updates): void {
    // Revive does all the same things as Upgrade, except it also removes end_date and sets status
    $updates['end_date'] = '';
    $updates['status_id:name'] = 'Current';
    parent::updateContract($updates);
  }

}
