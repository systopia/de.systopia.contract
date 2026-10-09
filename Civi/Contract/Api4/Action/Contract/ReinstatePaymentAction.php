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

use Civi\Api4\Contract;
use Civi\Api4\ContributionRecur;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\Membership;
use Civi\Api4\SepaMandate;
use Civi\Contract\Change\Type\PaymentReinstatedChange;
use Civi\Contract\Change\Type\UpdateChange;
use Civi\Contract\ContractManager;

/**
 * @method bool getCollectOutstanding()
 * @method $this setCollectOutstanding(bool $collectOutstanding)
 * @method int getId()
 * @method $this setId(int $id)
 * @method string getNotes()
 * @method $this setNotes(string $notes)
 * @method bool getReplaceSepaMandate()
 * @method $this setReplaceSepaMandate(bool $replaceSepaMandate)
 *
 * @method $this setIban(string|null $iban)
 * @method $this setBic(string|null $bic)
 * @method $this setAccountHolder(string|null $accountHolder)
 * @method $this setPaymentAmount(float|null $paymentAmount)
 * @method $this setPaymentFrequency(int|null $paymentFrequency)
 * @method $this setCycleDay(int|null $cycleDay)
 */
final class ReinstatePaymentAction extends AbstractAction {

  /**
   * @var bool
   */
  protected bool $collectOutstanding = FALSE;

  /**
   * @var int
   * @required
   */
  protected ?int $id = NULL;

  /**
   * @var string
   */
  protected string $notes = '';

  /**
   * @var bool
   */
  protected bool $replaceSepaMandate = FALSE;

  protected ?string $iban = NULL;

  protected ?string $bic = NULL;

  protected ?string $accountHolder = NULL;

  protected ?float $paymentAmount = NULL;

  protected ?int $paymentFrequency = NULL;

  protected ?int $cycleDay = NULL;

  public function __construct() {
    parent::__construct('Contract', 'reinstatePayment');
  }

  /**
   * @inheritDoc
   */
  public function _run(Result $result): void {
    /**
     * @phpstan-var array{
     *   id: int,
     *   campaign_id: ?int,
     *   membership_type_id: int,
     *   "status_id:name": string,
     *   "membership_payment.membership_recurring_contribution": ?int,
     *   "membership_payment.membership_frequency": int,
     * }|null $membership
     */
    $membership = Membership::get($this->getCheckPermissions())
      ->addSelect(
        'id',
        'campaign_id',
        'membership_type_id',
        'status_id:name',
        'membership_payment.membership_recurring_contribution',
        'membership_payment.membership_frequency',
      )
      ->addWhere('id', '=', $this->id)
      ->execute()
      ->first();

    if (NULL === $membership) {
      return;
    }

    try {
      $status = \CRM_Core_Session::singleton()->getStatus();
      \CRM_Core_Transaction::create()->run(fn() => $result[] = $this->reinstatePayment($membership));
    }
    catch (\Exception $e) {
      \CRM_Core_Session::singleton()->set('status', $status);

      throw $e;
    }
  }

  /**
   * @phpstan-param array{
   *   id: int,
   *   campaign_id: ?int,
   *   membership_type_id: int,
   *   "status_id:name": string,
   *   "membership_payment.membership_recurring_contribution": ?int,
   *   "membership_payment.membership_frequency": int,
   * } $membership
   *
   * @return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   */
  private function reinstatePayment(array $membership): array {
    if (!in_array($membership['status_id:name'], PaymentReinstatedChange::getStartStatusList(), TRUE)) {
      throw new \RuntimeException(
        "Payment of contracts in status {$membership['status_id:name']} cannot be reinstated"
      );
    }

    if (!isset($membership['membership_payment.membership_recurring_contribution'])) {
      throw new \RuntimeException("Membership {$membership['id']} does not use a SEPA mandate");
    }

    /** @var array{id: int, account_holder: ?string}|null $sepaMandate */
    $sepaMandate = SepaMandate::get(FALSE)
      ->addSelect('id', 'account_holder')
      ->addWhere('entity_table', '=', 'civicrm_contribution_recur')
      ->addWhere('entity_id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->execute()
      ->first();

    if (NULL === $sepaMandate) {
      throw new \RuntimeException("Membership {$membership['id']} does not use a SEPA mandate");
    }

    Membership::update(FALSE)
      ->addWhere('id', '=', $membership['id'])
      ->addValue('status_id:name', 'Current')
      ->execute()
      ->single();

    if (!$this->getReplaceSepaMandate()) {
      SepaMandate::reinstate(FALSE)
        ->addWhere('id', '=', $sepaMandate['id'])
        ->execute()
        ->single();
    }
    else {
      $this->replaceSepaMandate($membership, $sepaMandate);
    }

    if ($this->getCollectOutstanding()) {
      SepaMandate::collectOutstanding(FALSE)
        ->addWhere('id', '=', $sepaMandate['id'])
        ->execute();
    }

    ContractManager::getInstance()->createContractChange($membership['id'], [
      'activity_type_id:name' => PaymentReinstatedChange::getActivityTypeName(),
      'details' => str_replace("\n", '<br>', htmlentities($this->getNotes(), ENT_SUBSTITUTE)),
    ]);

    return [
      'id' => $membership['id'],
      'status_id:name' => 'Current',
    ];
  }

  /**
   * @phpstan-param array{
   *   id: int,
   *   campaign_id: ?int,
   *   membership_type_id: int,
   *   "membership_payment.membership_recurring_contribution": int,
   *   "membership_payment.membership_frequency": int,
   * } $membership
   * @param array{id: int, account_holder: ?string} $sepaMandate
   *
   * @throws \CRM_Core_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  private function replaceSepaMandate(array $membership, array $sepaMandate): void {
    if (NULL === $this->iban) {
      throw new \RuntimeException('IBAN is required to replace a SEPA mandate');
    }

    /** @var array{amount: float, cycle_day: int} $contributionRecur */
    $contributionRecur = ContributionRecur::get(FALSE)
      ->addSelect('amount', 'cycle_day')
      ->addWhere('id', '=', $membership['membership_payment.membership_recurring_contribution'])
      ->execute()
      ->single();

    Contract::modifyFull(FALSE)
      ->setThrowExceptionOnFailure(TRUE)
      ->setValues([
        'id' => $membership['id'],
        'action' => UpdateChange::getActionName(),
        'note' => str_replace("\n", '<br>', htmlentities($this->getNotes(), ENT_SUBSTITUTE)),
        'campaign_id' => $membership['campaign_id'],
        'membership_type_id' => $membership['membership_type_id'],
        'iban' => $this->iban,
        'bic' => $this->bic,
        'payment_option' => 'RCUR',
        'payment_amount' => $this->paymentAmount ?? $contributionRecur['amount'],
        'payment_frequency' => $this->paymentFrequency ?? $membership['membership_payment.membership_frequency'],
        'cycle_day' => $this->cycleDay ?? $contributionRecur['cycle_day'],
        'account_holder' => $this->accountHolder ?? $sepaMandate['account_holder'],
      ])
      ->execute();
  }

}
