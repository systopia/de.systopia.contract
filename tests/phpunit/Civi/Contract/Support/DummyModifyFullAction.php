<?php

declare(strict_types = 1);

namespace Civi\Contract\Support;

use Civi\Contract\Api4\Action\Contract\ModifyFullAction;

final class DummyModifyFullAction extends ModifyFullAction {

  /**
   * @param array<string, mixed> $item
   *
   * @return array<string, mixed>
   */
  public function runWriteRecord(array $item): array {
    return $this->writeRecord($item);
  }

}
