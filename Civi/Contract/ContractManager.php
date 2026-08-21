<?php
/*
 * Copyright (C) 2025 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Contract;

use Civi\Api4\Membership;
use Civi\Contract\ContractChange\ContractChangeFactory;
use Civi\Contract\ContractChange\ContractChangeInterface;
use Civi\Contract\ContractChange\SchedulableContractChangeInterface;

/**
 * @phpstan-import-type changeT from \CRM_Contract_Change
 */
class ContractManager {

  /**
   * @phpstan-var array<int, \Civi\Contract\Contract>
   */
  private array $contracts = [];

  public static function getInstance(): self {
    /** @var self */
    return \Civi::service(self::class);
  }

  public function __construct(
    private readonly ContractChangeFactory $contractChangeFactory
  ) {}

  public function getContract(int $membershipId): Contract {
    if (!isset($this->contracts[$membershipId])) {
      $this->contracts[$membershipId] = Contract::create($membershipId);
    }
    if (!isset($this->contracts[$membershipId])) {
      throw new \RuntimeException('Could not retrieve contract for membership with ID ' . $membershipId);
    }
    return $this->contracts[$membershipId];
  }

  public function getOwnerContract(int $relatedMembershipId): Contract {
    $relatedMembership = Membership::get(FALSE)
      ->addSelect('owner_membership_id')
      ->addWhere('id', '=', $relatedMembershipId)
      ->execute()
      ->single();
    if (!isset($relatedMembership['owner_membership_id'])) {
      throw new \RuntimeException('Membership with ID ' . $relatedMembershipId . ' is not a related membership');
    }
    return $this->getContract($relatedMembership['owner_membership_id']);
  }

  /**
   * @param int $contractId
   *   The ID of the membership which to add a related membership to.
   * @param int $contactId
   *   The ID of the contact which to add a related membership for.
   * @param \DateTimeInterface|null $startDate
   *   The start date of the related membership. Defaults to today.
   *
   * @return int
   *   The ID of the new related membership.
   */
  public function addRelatedMembership(int $contractId, int $contactId, ?\DateTimeInterface $startDate = NULL): int {
    $startDate ??= \date_create('today');
    $contract = $this->getContract($contractId);
    $relatedMembership = Membership::create(FALSE)
      ->addValue('owner_membership_id', $contract->getMembershipId())
      ->addValue('contact_id', $contactId)
      ->addValue('membership_type_id', $contract->getMembershipTypeId())
      ->addValue('start_date', $startDate->format('Y-m-d'))
      // TODO: Set more values?
      ->execute()
      ->single();

    $this->createContractChange(
      $contract->getMembershipId(),
      [
        'activity_type_id:name' => \CRM_Contract_Change_AddRelatedMembership::getActivityTypeName(),
        'activity_date_time' => $startDate->format('Y-m-d H:i:s'),
      ]
    );

    return $relatedMembership['id'];
  }

  public function endRelatedMembership(int $relatedMembershipId, ?\DateTimeInterface $endDate = NULL): void {
    $endDate ??= \date_create('today');
    Membership::update(FALSE)
      ->addWhere('id', '=', $relatedMembershipId)
      ->addValue('end_date', $endDate->format('Y-m-d'))
      ->execute();

    $contract = $this->getOwnerContract($relatedMembershipId);
    $this->createContractChange(
      $contract->getMembershipId(),
      [
        'activity_type_id:name' => \CRM_Contract_Change_EndRelatedMembership::getActivityTypeName(),
        'activity_date_time' => $endDate->format('Y-m-d H:i:s'),
      ]
    );
  }

  /**
   * @phpstan-param changeT $changeData
   *
   * @throws \CRM_Core_Exception
   */
  public function createContractChange(
    int $membershipId,
    array $changeData
  ): ContractChangeInterface {
    $change = $this->contractChangeFactory->create($changeData);
    $change->setParameter('source_contact_id', \CRM_Contract_Configuration::getUserID());
    $change->setParameter('contract_activity.contract_id', $membershipId);
    $change->setParameter('source_record_id', $membershipId);
    $change->setParameter('target_contact_id', $change->getContract()['contact_id']);
    $change->setStatus($change instanceof SchedulableContractChangeInterface ? 'Scheduled' : 'Completed');
    $change->populateData();
    if ($change instanceof SchedulableContractChangeInterface) {
      $change->verifyData();
      $change->shouldBeAccepted();
    }
    $change->save();
    return $change;
  }

}
