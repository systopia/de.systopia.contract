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

namespace Civi\Contract\Api4\Action\Contract;

use Civi\Api4\Contract;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Contract\ContractManager;

/**
 * @method $this setContractId(int $contractId)
 * @method $this setContactId(int $contactId)
 * @method $this setStartDate(string|null $startDate)
 */
class AddRelatedMembershipAction extends AbstractAction {

  private ContractManager $contractManager;

  /**
   * ID of parent membership
   *
   * @var int
   * @required
   */
  protected $contractId;

  /**
   * ID of the contact to add as related member
   *
   * @var int
   * @required
   */
  protected $contactId;

  /**
   * The start date of the related membership
   *
   * @var string|null
   */
  protected ?string $startDate = NULL;

  public function __construct(ContractManager $contractManager) {
    parent::__construct(Contract::getEntityName(), 'addRelatedMembership');
    $this->contractManager = $contractManager;
  }

  /**
   * @inheritDoc
   */
  public function _run(Result $result): void {
    if (isset($this->startDate)) {
      $startDate = \DateTime::createFromFormat('Y-m-d', $this->startDate);
      if (FALSE === $startDate) {
        throw new \RuntimeException('Invalid date format for parameter "startDate".');
      }
    }
    $relatedMembershipId = $this->contractManager->addRelatedMembership(
      $this->contractId,
      $this->contactId,
      $startDate ?? NULL
    );
    $result->exchangeArray(['id' => $relatedMembershipId]);
  }

}
