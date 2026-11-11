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
use Civi\Contract\Change\ContractChangeTypeContainer;
use Civi\Contract\Change\SchedulableContractChangeInterface;
use Civi\Contract\ContractManager;

/**
 * @method  string getAction()
 * @method $this setAction(string $action)
 * @method string|null getDate()
 * @method $this setDate(string|null $date)
 * @method int getId()
 * @method $this setId(int $id)
 * @method array<string, mixed> getValues()
 * @method $this setValues(array<string, mixed> $values)
 *
 * @phpstan-import-type changeT from \Civi\Contract\Change\ContractChangeInterface
 */
final class ModifyAction extends AbstractAction {

  /**
   * @var string
   * @required
   */
  protected string $action = '';

  /**
   * @var string|null
   */
  protected ?string $date = NULL;

  /**
   * @var int
   * @required
   */
  protected ?int $id = NULL;

  /**
   * @var array
   * @phpstan-var array<string, mixed>
   */
  protected array $values = [];

  public function __construct() {
    parent::__construct('Contract', 'modify');
  }

  public function _run(Result $result): void {
    $changeData = $this->createChangeData();
    $transaction = \CRM_Core_Transaction::create();
    $transaction->run(
      fn() => $result->exchangeArray([$this->persist($changeData)])
    );
  }

  /**
   * @phpstan-return changeT
   */
  private function createChangeData(): array {
    $changeData = $this->values;
    if (isset($changeData['note'])) {
      $changeData['details'] = $changeData['note'];
      unset($changeData['note']);
    }

    $executionTime = strtotime($this->date ?? 'now');
    if (FALSE === $executionTime) {
      throw new \RuntimeException('Invalid date');
    }

    $executionTime = \CRM_Contract_Form_Settings::adjustRequestedExecutionTime($executionTime);
    if ($executionTime < strtotime('today')) {
      throw new \RuntimeException(
        "Parameter 'date' rejected because it's too far in the past, see the 'date_adjustment' setting."
      );
    }

    $changeData['activity_date_time'] = date('Y-m-d H:i:s', $executionTime);

    $changeData['activity_type_id:name'] = ContractChangeTypeContainer::getInstance()
      ->getClassForAction($this->getAction())::getActivityTypeName();

    // @phpstan-ignore return.type
    return $changeData;
  }

  /**
   * @phpstan-param changeT $changeData
   *
   * @return array<string, mixed> Contract
   *
   * @throws \CRM_Core_Exception
   */
  private function persist(array $changeData): array {
    $contractManager = ContractManager::getInstance();
    $change = $contractManager->createContractChange(
      $this->getId(),
      $changeData
    );
    assert($change instanceof SchedulableContractChangeInterface);

    // make sure any newly created conflicts are marked
    $change->checkForConflicts();

    $executionTime = strtotime($changeData['activity_date_time']);
    if ($executionTime <= strtotime('now')) {
      /**
       * @var array{
       *   order: list<int>,
       *   completed: list<int>,
       *   failed: list<int>,
       *   error_details: array<int, string>,
       *   error_messages: array<int, string>,
       * } $processResult
       */
      // @phpstan-ignore offsetAccess.nonOffsetAccessible
      $processResult = civicrm_api3(
        'Contract',
        'process_scheduled_modifications',
        ['id' => $this->getId()]
      )['values'];

      if ([] !== $processResult['failed']) {
        throw new \RuntimeException(implode(', ', $processResult['error_messages']));
      }
    }

    return $change->getContract();
  }

}
