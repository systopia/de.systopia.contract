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

final class CRM_Contract_Page_SuspendPayment extends CRM_Core_Page {

  public function run(): void {
    /** @var \Civi\Angular\AngularLoader $angularLoader */
    $angularLoader = Civi::service('angularjs.loader');
    $angularLoader->addModules(['contract']);

    $this->assign('id', CRM_Utils_Request::retrieve('id', 'Positive', abort: TRUE));

    parent::run();
  }

}
