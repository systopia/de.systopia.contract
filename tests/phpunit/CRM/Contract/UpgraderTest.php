<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2026 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Api4\Activity;

/**
 * @group headless
 *
 * @covers \CRM_Contract_Upgrader::migrateContractReferences
 */
class CRM_Contract_UpgraderTest extends CRM_Contract_ContractTestBase {

  public function testMigrateContractReferences_WithExistingContract_SetsReference(): void {
    $contractId = (int) $this->createNewContract()['id'];
    $activityId = $this->createUnmigratedContractActivity($contractId);

    $this->runMigration(0, PHP_INT_MAX);

    self::assertSame($contractId, $this->getContractReference($activityId));
  }

  public function testMigrateContractReferences_WithMissingContract_DeletesActivity(): void {
    $activityId = $this->createUnmigratedContractActivity(999999999);

    $this->runMigration(0, PHP_INT_MAX);

    self::assertNull($this->getActivity($activityId));
  }

  public function testMigrateContractReferences_OutsideIdRange_LeavesReferenceEmpty(): void {
    $contractId = (int) $this->createNewContract()['id'];
    $activityId = $this->createUnmigratedContractActivity($contractId);

    $this->runMigration(0, $activityId - 1);

    self::assertNull($this->getContractReference($activityId));
  }

  public function testMigrateContractReferences_RunTwice_IsIdempotent(): void {
    $contractId = (int) $this->createNewContract()['id'];
    $activityId = $this->createUnmigratedContractActivity($contractId);

    $this->runMigration(0, PHP_INT_MAX);
    $this->runMigration(0, PHP_INT_MAX);

    self::assertSame($contractId, $this->getContractReference($activityId));
  }

  private function runMigration(int $fromId, int $toId): void {
    $ctx = new CRM_Queue_TaskContext();
    $ctx->log = CRM_Core_Error::createDebugLogger();
    CRM_Contract_Upgrader::_queueAdapter(
      $ctx,
      'de.systopia.contract',
      'migrateContractReferences',
      $fromId,
      $toId
    );
  }

  private function createUnmigratedContractActivity(int $sourceRecordId): int {
    $activityTypeIds = CRM_Contract_Change::getActivityTypeIds();
    $activity = Activity::create(FALSE)
      ->addValue('activity_type_id', reset($activityTypeIds))
      ->addValue('source_record_id', $sourceRecordId)
      ->addValue('source_contact_id', $this->createContactWithRandomEmail()['id'])
      ->addValue('subject', 'Upgrader test activity')
      ->execute()
      ->single();

    return (int) $activity['id'];
  }

  private function getContractReference(int $activityId): ?int {
    $activity = $this->getActivity($activityId);

    return isset($activity['contract_activity.contract_id'])
      ? (int) $activity['contract_activity.contract_id']
      : NULL;
  }

  /**
   * @phpstan-return array{
   *   "contract_activity.contract_id": int|numeric-string,
   * }|null
   */
  private function getActivity(int $activityId): ?array {
    try {
      // @phpstan-ignore return.type
      return Activity::get(FALSE)
        ->addSelect('contract_activity.contract_id')
        ->addWhere('id', '=', $activityId)
        ->execute()
        ->single();
    }
    catch (\Exception $exception) {
      // @ignoreException
      return NULL;
    }
  }

}
