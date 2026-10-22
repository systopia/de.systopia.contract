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

namespace Civi\Contract\Api4\Action\Contract;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Membership;
use Civi\Api4\SepaMandate;
use Civi\Contract\Change\Type\PaymentSuspendedChange;
use Civi\Contract\ContractManager;

/**
 * @method int getId()
 * @method $this setId(int $id)
 * @method string getNotes()
 * @method $this setNotes(string $notes)
 * @method string getReason()
 * @method $this setReason(string $reason)
 */
final class SuspendPaymentAction extends AbstractAction {

  /**
   * @var int
   * @required
   */
  protected ?int $id = NULL;

  protected string $notes = '';

  /**
   * @var string
   * @required
   */
  protected string $reason = '';

  public function __construct() {
    parent::__construct('Contract', 'suspendPayment');
  }

  public function _run(Result $result): void {
    /**
     * @phpstan-var array{
     *   id: int,
     *   "status_id:name": string,
     *   "membership_payment.membership_recurring_contribution": ?int,
     * }|null $membership
     */
    $membership = Membership::get($this->getCheckPermissions())
      ->addSelect('id', 'status_id:name', 'membership_payment.membership_recurring_contribution')
      ->addWhere('id', '=', $this->id)
      ->execute()
      ->first();

    if (NULL === $membership) {
      return;
    }

    \CRM_Core_Transaction::create()->run(fn() => $result[] = $this->suspendPayment($membership));
  }

  /**
   * @phpstan-param array{
   *   id: int,
   *   "status_id:name": string,
   *   "membership_payment.membership_recurring_contribution": ?int,
   * } $membership
   *
   * @return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   */
  private function suspendPayment(array $membership): array {
    if (!in_array($membership['status_id:name'], PaymentSuspendedChange::getStartStatusList(), TRUE)) {
      throw new \RuntimeException("Payment of contracts in status {$membership['status_id:name']} cannot be suspended");
    }

    if (!isset($membership['membership_payment.membership_recurring_contribution'])) {
      throw new \RuntimeException("Membership {$membership['id']} does not use a SEPA mandate");
    }

    /** @var array{id: int}|null $sepaMandate */
    $sepaMandate = SepaMandate::get(FALSE)
      ->addSelect('id')
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addWhere('entity_id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->execute()
      ->first();

    if (NULL === $sepaMandate) {
      throw new \RuntimeException("Membership {$membership['id']} does not use a SEPA mandate");
    }

    SepaMandate::suspend(FALSE)
      ->addWhere('id', '=', $sepaMandate['id'])
      ->execute()
      ->single();

    Membership::update(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addValue('status_id:name', 'PaymentSuspended')
      ->execute()
      ->single();

    ContractManager::getInstance()->createContractChange($membership['id'], [
      'activity_type_id:name' => PaymentSuspendedChange::getActivityTypeName(),
      'details' => str_replace("\n", '<br>', htmlentities($this->getNotes(), ENT_SUBSTITUTE)),
      'contract_payment_suspended.reason' => $this->getReason(),
    ]);

    return [
      'id' => $membership['id'],
      'status_id:name' => 'PaymentSuspended',
    ];
  }

}
