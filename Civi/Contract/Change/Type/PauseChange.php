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

use Civi\Contract\Change\AbstractSchedulableContractChange;
use Civi\Contract\Change\ActionMenuEntry;
use Civi\Contract\Change\ContractChangeFactory;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Pause Membership" change
 *
 * @phpstan-type changeT array{
 *   id?: int,
 *   "activity_type_id:name"?: string,
 *   activity_type_id?: int,
 *   activity_date_time?: string,
 *   status_id?: int|string,
 *   source_record_id?: int,
 *   source_contact_id?: int,
 *   target_contact_id?: int,
 *   subject?: string,
 *   "contract_activity.contract_id"?: int,
 *   activity_date_time: string,
 *   resume_date?: string,
 * }
 *
 * @extends AbstractSchedulableContractChange<changeT>
 */
class PauseChange extends AbstractSchedulableContractChange {

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

    $resumeDate = $this->getParameter('resume_date', '');
    if ('' === $resumeDate) {
      $resumeDate = $this->getParameter('activity_date_time', date('Y-m-d'));
      /** @var int $resumeTime */
      $resumeTime = strtotime("{$resumeDate} + 1 day");
      $this->setParameter('resume_date', date('Y-m-d', $resumeTime));
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
      /** @var string $resumeDate */
      $resumeDate = $this->getParameter('resume_date', '');
      if ('' !== $resumeDate) {
        $contract = $this->getContract();
        $resumeChange = ContractChangeFactory::getInstance()->create([
          'activity_type_id:name' => ResumeChange::getActivityTypeName(),
        ]);
        $resumeChange->setParameter('activity_date_time', $resumeDate);
        $resumeChange->setParameter('contract_activity.contract_id', $this->getContractID());
        $resumeChange->setParameter('source_record_id', $this->getContractID());
        // @phpstan-ignore argument.type
        $resumeChange->setParameter('source_contact_id', $this->getParameter('source_contact_id'));
        // @phpstan-ignore argument.type
        $resumeChange->setParameter('target_contact_id', $contract['contact_id']);
        $resumeChange->setParameter('subject', $resumeChange->getSubject($contract));
        $resumeChange->setStatus('Scheduled');
        $resumeChange->save();
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
    $paymentContractId = $contract['membership_payment.membership_recurring_contribution'] ?? NULL;
    if (NULL !== $paymentContractId) {
      \CRM_Contract_SepaLogic::pauseSepaMandate($paymentContractId);
      $this->updateContract(['status_id:name' => 'Paused']);
    }

    // update change activity
    $contractAfter = $this->getContract(TRUE);
    $this->setParameter('subject', $this->getSubject($contractAfter, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * @inheritDoc
   */
  public function verifyData(): void {
    parent::verifyData();
    /** @var string $resumeDate */
    $resumeDate = $this->getParameter('resume_date', '');
    if ('' !== $resumeDate) {
      // check that the resume date is not on the same day as the pause
      // @phpstan-ignore argument.type
      $pauseDate = date('Y-m-d', strtotime($this->data['activity_date_time']));
      // @phpstan-ignore argument.type
      $resumeDate = date('Y-m-d', strtotime($resumeDate));
      if ($pauseDate >= $resumeDate) {
        throw new \RuntimeException(E::ts('Resume date cannot be before or on the same day as the pause.'));
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    $resume = $this->getParameter('resume_date');
    if ($this->isNew()) {
      return NULL !== $resume
        ? E::ts('Pause contract until %1', [
          // @phpstan-ignore argument.type
          1 => date('Y-m-d', strtotime($resume)),
        ])
        : E::ts('Pause contract');
    }
    return NULL !== $resume
      ? E::ts('Contract paused until %1', [
        // @phpstan-ignore argument.type
        1 => date('Y-m-d', strtotime($resume)),
      ])
      : E::ts('Contract paused');
  }

}
