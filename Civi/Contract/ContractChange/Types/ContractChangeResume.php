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

namespace Civi\Contract\ContractChange\Types;

use Civi\Contract\ContractChange\AbstractSchedulableContractChange;
use Civi\Contract\ContractChange\ActionMenuEntry;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Resume Membership" change
 */
class ContractChangeResume extends AbstractSchedulableContractChange {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-play')
      ->setWeight(20);
  }

  public static function getActionName(): string {
    return 'resume';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Resumed';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-play-circle-o';
  }

  public static function getStartStatusList(): array {
    return ['Paused'];
  }

  public static function getTitle(): string {
    return E::ts('Resume Contract');
  }

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return list<string>
   */
  public function getRequiredFields(): array {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function execute(): void {
    $contract = $this->getContract(TRUE);

    // pause the mandate
    $payment_contract_id = $contract['membership_payment.membership_recurring_contribution'] ?? NULL;
    if (NULL !== $payment_contract_id) {
      \CRM_Contract_SepaLogic::resumeSepaMandate($payment_contract_id);
      $this->updateContract(['status_id:name' => 'Current']);
    }

    // update change activity
    $contract_after = $this->getContract(TRUE);
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    if ($this->isNew()) {
      return E::ts('Resume contract');
    }
    return E::ts('Contract resumed');
  }

}
