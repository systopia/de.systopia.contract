<?php

declare(strict_types = 1);

namespace Systopia\Contract\Tests\Support;

use Civi\Contract\Api4\Action\Contract\CreateFullAction;

class DummyCreateFullAction extends CreateFullAction {

  /**
   * @param array<string, mixed> $item
   * @return array<string, mixed>
   * @throws \Civi\API\Exception\NotImplementedException
   */
  public function runWriteRecord(array $item): array {
    return $this->writeRecord($item);
  }

}
