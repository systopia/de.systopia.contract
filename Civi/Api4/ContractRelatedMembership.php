<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
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
use Civi\Contract\Api4\Action\ContractRelatedMembership\GetAction;
use Civi\Contract\Api4\Action\ContractRelatedMembership\GetFieldsAction;
use Civi\Contract\Api4\Action\ContractRelatedMembership\CreateAction;
use Civi\Contract\Api4\Action\ContractRelatedMembership\SaveAction;
use Civi\Contract\Api4\Action\ContractRelatedMembership\UpdateAction;

/**
 * APIv4 entity for being able to create Afform forms for related memberships. It does not have fully specified CRUD
 * actions.
 */
class ContractRelatedMembership extends AbstractEntity {

  public static function getFields(bool $checkPermissions = TRUE): GetFieldsAction {
    return (new GetFieldsAction())->setCheckPermissions($checkPermissions);
  }

  public static function get(bool $checkPermissions = TRUE): GetAction {
    return (new GetAction())->setCheckPermissions($checkPermissions);
  }

  public static function create(bool $checkPermissions = TRUE): CreateAction {
    return (new CreateAction())->setCheckPermissions($checkPermissions);
  }

  public static function update(bool $checkPermissions = TRUE): UpdateAction {
    return (new UpdateAction())->setCheckPermissions($checkPermissions);
  }

  public static function save(bool $checkPermissions = TRUE): SaveAction {
    return (new SaveAction())->setCheckPermissions($checkPermissions);
  }

}
