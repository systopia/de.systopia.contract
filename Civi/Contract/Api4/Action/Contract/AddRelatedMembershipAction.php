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

class AddRelatedMembershipAction extends AbstractAction {

  private ContractManager $contractManager;

  /**
   * ID of parent membership
   *
   * @var int
   * @required
   */
  protected $membershipId;

  /**
   * ID of the contact to add as related member
   *
   * @var int
   * @required
   */
  protected $contactId;

  public function __construct(ContractManager $contractManager) {
    parent::__construct(Contract::getEntityName(), 'addRelatedMembership');
    $this->contractManager = $contractManager;
  }

  /**
   * @inheritDoc
   */
  public function _run(Result $result): void {
    $relatedMembershipId = $this->contractManager->addRelatedMembership($this->membershipId, $this->contactId);
    $result->exchangeArray(['id' => $relatedMembershipId]);
  }

}
