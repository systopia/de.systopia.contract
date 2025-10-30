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

namespace Civi\Api4;

use Civi\Api4\Generic\AbstractEntity;
use Civi\Contract\Api4\Action\Contract\AddRelatedMemberAction;
use Civi\Contract\Api4\Action\Contract\EndRelatedMemberAction;
use Civi\Contract\Api4\Action\Contract\CreateFullAction;
use Civi\Contract\Api4\Action\Contract\GetFieldsAction;
use Civi\Contract\Api4\Action\Contract\ModifyFullAction;

class Contract extends AbstractEntity {

  /**
   * @inheritDoc
   */
  public static function getFields() {
    return new GetFieldsAction();
  }

  /**
   * @return array<string, array<string|array<string>>>
   */
  public static function permissions(): array {
    return [
      'meta' => ['access CiviCRM'],
      'default' => ['access CiviCRM'],
    ];
  }

  public static function createfull(bool $checkPermissions = TRUE): CreateFullAction {
    return (new CreateFullAction())->setCheckPermissions($checkPermissions);
  }

  public static function modifyFull(bool $checkPermissions = TRUE): ModifyFullAction {
    return (new ModifyFullAction())->setCheckPermissions($checkPermissions);
  }

  public static function addRelatedMember(bool $checkPermissions = TRUE): AddRelatedMemberAction {
    return \Civi::service(AddRelatedMemberAction::class)->setCheckPermissions($checkPermissions);
  }

  public static function endRelatedMember(bool $checkPermissions = TRUE): AddRelatedMemberAction {
    return \Civi::service(EndRelatedMemberAction::class)->setCheckPermissions($checkPermissions);
  }

}
