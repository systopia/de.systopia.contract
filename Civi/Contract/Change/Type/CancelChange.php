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

use Civi\Api4\Activity;
use Civi\Api4\MembershipStatus;
use Civi\Contract\Change\AbstractSchedulableContractChange;
use Civi\Contract\Change\ActionMenuEntry;
use Civi\Contract\Change\ContractChangeTypeContainer;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Cancel Membership" change
 */
class CancelChange extends AbstractSchedulableContractChange {

  private const MEMBERSHIP_CANCEL_REASON = 'membership_cancellation.membership_cancel_reason';
  private const MEMBERSHIP_CANCEL_DATE   = 'membership_cancellation.membership_cancel_date';

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-times')
      ->setWeight(1000)
      ->setStyle('danger');
  }

  public static function getActionName(): string {
    return 'cancel';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Cancelled';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-stop-circle-o';
  }

  public static function getStartStatusList(): array {
    return ['New', 'Grace', 'Current', 'Pending'];
  }

  public static function getTitle(): string {
    return E::ts('Cancel Contract');
  }

  /**
   * Get a list of required fields for this type
   *
   * @phpstan-return list<string>
   */
  public function getRequiredFields(): array {
    return [
      self::MEMBERSHIP_CANCEL_REASON,
    ];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData(): void {
    if ($this->isNew()) {
      $this->setParameter(
        'contract_cancellation.contact_history_cancel_reason',
        $this->getParameter(self::MEMBERSHIP_CANCEL_REASON)
      );
      $this->setParameter('subject', $this->getSubject(NULL));
    }
    else {
      parent::populateData();
      $this->setParameter(
        self::MEMBERSHIP_CANCEL_REASON,
        $this->getParameter('contract_cancellation.contact_history_cancel_reason')
      );
    }
  }

  /**
   * @inheritDoc
   */
  public function execute(): void {
    $contract = $this->getContract();

    // cancel the contract by setting the end date
    $contract_update = [
      'end_date'                     => date('YmdHis'),
      // @phpstan-ignore offsetAccess.notFound
      self::MEMBERSHIP_CANCEL_REASON => $this->data[self::MEMBERSHIP_CANCEL_REASON],
      self::MEMBERSHIP_CANCEL_DATE   => date('YmdHis'),
      'status_id:name'               => 'Cancelled',
    ];

    // perform the update
    $this->updateContract($contract_update);

    // also: cancel the mandate/recurring contribution
    \CRM_Contract_SepaLogic::terminateSepaMandate(
      $contract['membership_payment.membership_recurring_contribution'],
      // @phpstan-ignore offsetAccess.notFound
      $this->data[self::MEMBERSHIP_CANCEL_REASON]
    );

    // update change activity
    $contract_after = $this->getContract();
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * @inheritDoc
   */
  public function shouldBeAccepted(): void {
    parent::shouldBeAccepted();

    // check for OTHER CANCELLATION REQUEST for the same day
    //  @see https://redmine.greenpeace.at/issues/1190
    // @phpstan-ignore offsetAccess.notFound
    $activityDateTime = strtotime($this->data['activity_date_time']);
    if (FALSE === $activityDateTime) {
      throw new \RuntimeException('Invalid activity date.');
    }
    $requested_day = date('Y-m-d', $activityDateTime);
    /** @phpstan-var list<array{id: int, activity_date_time: string}> $scheduled_activities */
    $scheduled_activities = \Civi\Api4\Activity::get(FALSE)
      ->addSelect('id', 'activity_date_time')
      ->addWhere('activity_date_time', 'IS NOT NULL')
      ->addWhere('contract_activity.contract_id', '=', $this->getContractID())
      ->addWhere('activity_type_id:name', '=', self::getActivityTypeName())
      ->addWhere('status_id:name', '=', 'Scheduled')
      ->execute()
      ->getArrayCopy();
    foreach ($scheduled_activities as $scheduled_activity) {
      /** @var int $scheduledActivityDateTime */
      $scheduledActivityDateTime = strtotime($scheduled_activity['activity_date_time']);
      $scheduled_for_day = date('Y-m-d', $scheduledActivityDateTime);
      if ($scheduled_for_day === $requested_day) {
        // There is already a scheduled 'cancel' activity for the same day.
        throw new \RuntimeException('Scheduling an (additional) cancellation request is not desired in this context.');
      }
    }

    // IF CONTRACT ALREADY CANCELLED, create another cancel activity only
    //  when there are other scheduled (or 'needs review') changes
    //  @see https://redmine.greenpeace.at/issues/1190
    $contract = $this->getContract();

    $contractCancelledStatus = MembershipStatus::get(FALSE)
      ->addWhere('name', '=', 'Cancelled')
      ->addSelect('id')
      ->execute()
      ->single();
    if ((int) $contract['status_id'] === (int) $contractCancelledStatus['id']) {
      // contract is cancelled
      $pendingActivityCount = Activity::get(FALSE)
        ->selectRowCount()
        ->addWhere('contract_activity.contract_id', '=', $this->getContractID())
        ->addWhere('activity_type_id:name', 'IN', ContractChangeTypeContainer::getInstance()->getActivityTypes())
        ->addWhere('status_id:name', 'IN', ['Scheduled', 'Needs Review'])
        ->execute()
        ->countMatched();
      if (0 === $pendingActivityCount) {
        throw new \RuntimeException('Scheduling an (additional) cancellation request is not desired in this context.');
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function renderSubject(?array $contractAfter, ?array $contractBefore = NULL): string {
    if ($this->isNew()) {
      return E::ts('Contract cancellation scheduled');
    }

    return isset($this->data['contract_cancellation.contact_history_cancel_reason'])
      ? E::ts(
        'Contract cancelled (%1)',
        [
          1 => $this->labelValue(
            $this->data['contract_cancellation.contact_history_cancel_reason'],
            'contract_cancellation.contact_history_cancel_reason'
          ),
        ]
      )
      : E::ts('Contract cancelled');
  }

}
