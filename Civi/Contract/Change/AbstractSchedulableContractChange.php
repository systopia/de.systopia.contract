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

namespace Civi\Contract\Change;

/**
 * Base class for schedulable contract changes.
 */
// phpcs:ignore Generic.Files.LineLength.TooLong
abstract class AbstractSchedulableContractChange extends AbstractContractChange implements SchedulableContractChangeInterface {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return new ActionMenuEntry(
      static::getTitle(),
      'civicrm/contract/modify?reset=1&id=[id]&modify_action=' . static::getActionName(),
    );
  }

  /**
   * @inheritDoc
   */
  public function shouldBeAccepted(): void {}

  /**
   * @inheritDoc
   */
  public function verifyData(): void {
    // simply check if all required fields are there
    // ...anything else needs to be checked in the specific class...
    $required_fields = $this->getRequiredFields();
    foreach ($required_fields as $required_field) {
      if (!isset($this->data[$required_field])) {
        throw new \RuntimeException("Parameter '{$required_field}' missing.");
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function verifyStatusChange(): void {
    $contract = $this->getContract();
    $status_name = \CRM_Contract_Utils::getMembershipStatusName($contract['status_id']);
    if (!in_array($status_name, $this::getStartStatusList(), TRUE)) {
      throw new \RuntimeException("Cannot {$this::getActionName()} a membership when its status is '{$status_name}'.");
    }
  }

  public function checkForConflicts(): void {
    // TODO: refactor CRM_Contract_Handler_ModificationConflicts
    $conflictHandler = new \CRM_Contract_Handler_ModificationConflicts();
    $conflictHandler->checkForConflicts($this->getContractID());
  }

}
