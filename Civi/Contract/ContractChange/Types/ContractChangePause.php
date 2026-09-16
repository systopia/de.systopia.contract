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
use Civi\Contract\ContractChange\ContractChangeFactory;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Pause Membership" change
 */
class ContractChangePause extends AbstractSchedulableContractChange {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-pause')
      ->setWeight(20);
  }

  public static function getActionName(): string {
    return 'pause';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Paused';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-pause-circle-o';
  }

  public static function getStartStatusList(): array {
    return ['New', 'Grace', 'Current'];
  }

  public static function getTitle(): string {
    return E::ts('Pause Contract');
  }

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return list<string>
   */
  public function getRequiredFields(): array {
    if ($this->isNew()) {
      return [
        'resume_date',
      ];
    }
    else {
      return [];
    }
  }

  /**
   * Derive/populate additional data
   */
  public function populateData(): void {
    $contract = $this->getContract();

    $resume_date = $this->getParameter('resume_date');
    if (!$resume_date) {
      $resume_date = $this->getParameter('activity_date_time', date('Y-m-d'));
      $this->setParameter('resume_date', date('Y-m-d', strtotime("{$resume_date} + 1 day")));
    }

    $this->setParameter('subject', $this->getSubject($contract));

    if (!$this->isNew()) {
      parent::populateData();
    }
  }

  /**
   * In this case, don't only just save the pause, but also the resume!
   */
  public function save(): void {
    if ($this->isNew()) {
      // create resume change activity:
      $resume_date = $this->getParameter('resume_date');
      if ($resume_date) {
        $contract = $this->getContract();
        $resume_change = ContractChangeFactory::getInstance()->create([
          'activity_type_id:name' => ContractChangeResume::getActivityTypeName(),
        ]);
        $resume_change->setParameter('activity_date_time', $resume_date);
        $resume_change->setParameter('contract_activity.contract_id', $this->getContractID());
        $resume_change->setParameter('source_record_id', $this->getContractID());
        $resume_change->setParameter('source_contact_id', $this->getParameter('source_contact_id'));
        $resume_change->setParameter('target_contact_id', $contract['contact_id']);
        $resume_change->setParameter('subject', $resume_change->getSubject($contract));
        $resume_change->setStatus('Scheduled');
        $resume_change->save();
      }
    }
    parent::save();
  }

  /**
   * @inheritDoc
   */
  public function execute(): void {
    $contract = $this->getContract(TRUE);

    // pause the mandate
    $payment_contract_id = $contract['membership_payment.membership_recurring_contribution'] ?? NULL;
    if (NULL !== $payment_contract_id) {
      \CRM_Contract_SepaLogic::pauseSepaMandate($payment_contract_id);
      $this->updateContract(['status_id:name' => 'Paused']);
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
  public function verifyData(): void {
    parent::verifyData();

    // check that the resume date is not on the same day as the pause
    $pause_date  = date('Y-m-d', strtotime($this->getParameter('activity_date_time')));
    $resume_date = date('Y-m-d', strtotime($this->getParameter('resume_date')));
    if ($pause_date >= $resume_date) {
      throw new \RuntimeException(E::ts('Resume date cannot be before or on the same day as the pause.'));
    }
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    $resume = $this->getParameter('resume_date');
    if ($this->isNew()) {
      return $resume
        ? E::ts('Pause contract until %1', [1 => date('Y-m-d', strtotime($resume))])
        : E::ts('Pause contract');
    }
    return $resume
      ? E::ts('Contract paused until %1', [1 => date('Y-m-d', strtotime($resume))])
      : E::ts('Contract paused');
  }

}
