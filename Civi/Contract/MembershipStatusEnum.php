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

namespace Civi\Contract;

use CRM_Contract_ExtensionUtil as E;

enum MembershipStatusEnum {

  case New;

  case Current;

  case Grace;

  case Pending;

  case Cancelled;

  case Deceased;

  case Paused;

  case PaymentSuspended;

  public function label(): string {
    return match($this) {
      self::New => E::ts('New'),
      self::Current => E::ts('Current'),
      self::Grace => E::ts('Grace'),
      self::Pending => E::ts('Pending'),
      self::Cancelled => E::ts('Cancelled'),
      self::Deceased => E::ts('Deceased'),
      self::Paused => E::ts('Paused'),
      self::PaymentSuspended => E::ts('Payment Suspended'),
    };
  }

  public function isActive(): bool {
    return match($this) {
      self::New => FALSE,
      self::Grace => FALSE,
      self::Paused => FALSE,
      default => TRUE,
    };
  }

  public function isCurrentMember(): bool {
    return match($this) {
      self::New => TRUE,
      self::Current => TRUE,
      self::Grace => TRUE,
      self::Pending => TRUE,
      self::Cancelled => FALSE,
      self::Deceased => FALSE,
      self::Paused => TRUE,
      self::PaymentSuspended => FALSE,
    };
  }

  public function isReserved(): bool {
    return match($this) {
      self::Pending => TRUE,
      self::Cancelled => TRUE,
      self::Deceased => TRUE,
      self::PaymentSuspended => TRUE,
      default => FALSE,
    };

  }

}
