<?php

declare(strict_types = 1);

namespace Systopia\Contract\Tests\Support;

use Civi\ActionProvider\Parameter\ParameterBag;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\Contract\ActionProvider\Action\CreateContract;

class DummyContract extends CreateContract {

  /**
   * @param array<string, mixed> $config
   */
  public function setTestConfiguration(array $config): void {
    $bag = new ParameterBag();
    foreach ($config as $name => $value) {
      $bag->setParameter($name, $value);
    }

    $this->configuration = $bag;
  }

  public function runDoAction(ParameterBagInterface $parameters, ParameterBagInterface $output): void {
    $this->doAction($parameters, $output);
  }

}
