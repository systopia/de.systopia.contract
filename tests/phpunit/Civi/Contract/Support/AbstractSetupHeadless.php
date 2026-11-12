<?php

declare(strict_types = 1);

namespace Civi\Contract\Support;

use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use PHPUnit\Framework\TestCase;
use Systopia\TestFixtures\Core\FixtureEntityStore;

abstract class AbstractSetupHeadless extends TestCase implements Test\HeadlessInterface, Test\TransactionalInterface {

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->installMe(__DIR__)
      ->install('action-provider')
      ->install('civi_campaign')
      ->install('org.project60.sepa')
      ->install('org.project60.banking')
      ->apply();
  }

  protected function setUp(): void {
    parent::setUp();
    $this->setUserPermissions([
      'access CiviCRM',
      'access CiviMember',
      'edit memberships',
      'access CiviContribute',
      'access all custom data',
      'view all contacts',
      'view sepa mandates',
    ]);
  }

  protected function tearDown(): void {
    parent::tearDown();
    FixtureEntityStore::reset();
  }

  /**
   * @param list<string>|null $permissions
   */
  protected function setUserPermissions(?array $permissions): void {
    $userPermissions = \CRM_Core_Config::singleton()->userPermissionClass;
    // @phpstan-ignore-next-line
    $userPermissions->permissions = $permissions;
  }

}
