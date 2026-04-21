<?php

declare(strict_types = 1);

namespace Civi\Contract\Support;

use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Core\FixtureEntityStore;

abstract class AbstractSetupHeadless extends TestCase implements Test\HeadlessInterface, Test\TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    FixtureEntityStore::reset();

    return Test::headless()
      ->installMe(__DIR__)
      ->install('civi_campaign')
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->apply();
  }

}
