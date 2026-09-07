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

namespace Civi\Contract\ContractChange;

/**
 * Represents an entry for the membership actions menu.
 */
final class ActionMenuEntry {

  /**
   * @param string $path
   *   May contain [id] as placeholder for the membership ID.
   */
  public function __construct(
    public string $title,
    public string $path,
    public int $weight = 100,
    public ?string $icon = NULL,
    public string $style = 'default',
  ) {}

  public function setTitle(string $title): self {
    $this->title = $title;

    return $this;
  }

  public function setPath(string $path): self {
    $this->path = $path;

    return $this;
  }

  public function setIcon(string $icon): self {
    $this->icon = $icon;

    return $this;
  }

  public function setWeight(int $weight): self {
    $this->weight = $weight;

    return $this;
  }

  public function setStyle(string $style): self {
    $this->style = $style;

    return $this;
  }

}
