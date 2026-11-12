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

namespace Civi\Contract\Form\Field;

class AdditionalFormField {

  public bool $required = FALSE;

  public ?string $default = NULL;

  /**
   * @var callable(mixed, array<string, mixed>): ?string|null
   *   Optional callable that receives the field value as first argument and an
   *   array containing all submitted values as second argument. Returns a
   *   string containing a validation message or NULL, if validation succeeded.
   */
  public $validateCallback = NULL;

  /**
   * @param string $fieldName APIv4 Membership field name.
   * @param string $inputType QuickForm input type.
   */
  public function __construct(
    public string $fieldName,
    public string $inputType,
    public string $label,
  ) {}

  public function setRequired(bool $required): static {
    $this->required = $required;

    return $this;
  }

  public function setDefault(?string $default): static {
    $this->default = $default;

    return $this;
  }

  /**
   * @param callable(mixed, array<string, mixed>): ?string|null $validateCallback
   *   Optional callback that receives the field value as first argument and an
   *   array containing all submitted values as second argument. Returns a
   *   string containing a validation message or NULL, if validation succeeded.
   */
  public function setValidateCallback(?callable $validateCallback): static {
    $this->validateCallback = $validateCallback;

    return $this;
  }

}
