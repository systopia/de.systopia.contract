<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Contract\ContractChange\ContractChangeTypeContainer;
use CRM_Contract_ExtensionUtil as E;
use Civi\Api4\Activity;
use Civi\Api4\OptionValue;

/**
 * Collection of upgrade steps.
 */
class CRM_Contract_Upgrader extends CRM_Extension_Upgrader_Base {

  private const CONTRACT_REFERENCE_BATCH_SIZE = 5000;

  public function postInstall(): void {
    $this->ensureNoPaymentRequiredPaymentInstrument();
  }

  public function enable(): void {
    $this->postInstall();
  }

  public function upgrade_1370(): bool {
    $this->ctx->log->info('Applying update 1370');
    return TRUE;
  }

  public function upgrade_1390(): bool {
    $this->ctx->log->info('Applying update 1390');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

  public function upgrade_2003(): bool {
    $this->ctx->log->info('Add "No Payment required" payment instrument.');
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2004(): bool {
    $this->ctx->log->info('Add ContractMembershipRelation entity schema.');
    E::schema()->createEntityTable('schema/ContractMembershipRelation.entityType.php');
    return TRUE;
  }

  public function upgrade_2005(): bool {
    return TRUE;
  }

  public function upgrade_2006(): bool {
    // Migrate "source_record_id" to custom field contract_activity.contract_id for contract activities.
    /** @var int $maxActivityId */
    $maxActivityId = Activity::get(FALSE)
      ->addSelect('MAX(id) AS max_id')
      ->addWhere('activity_type_id:name', 'IN', ContractChangeTypeContainer::getInstance()->getActivityTypes())
      ->execute()
      ->first()['max_id'] ?? 0;
    for ($fromId = 0; $fromId < $maxActivityId; $fromId += self::CONTRACT_REFERENCE_BATCH_SIZE) {
      $this->addTask(
        E::ts('Migrate contract references for activities from "source_record_id" to entity reference field'),
        'migrateContractReferences',
        $fromId,
        $fromId + self::CONTRACT_REFERENCE_BATCH_SIZE
      );
    }
    return TRUE;
  }

  /**
   * @param int $fromId
   *   Exclusive lower bound of the migrated activity ID range.
   * @param int $toId
   *   Inclusive upper bound of the migrated activity ID range.
   */
  public function migrateContractReferences(int $fromId, int $toId): bool {
    $contractIds = Activity::get(FALSE)
      ->addSelect('id', 'source_record_id', 'membership.id')
      ->addJoin('Membership AS membership', 'LEFT', NULL, ['membership.id', '=', 'source_record_id'])
      ->addWhere('activity_type_id:name', 'IN', ContractChangeTypeContainer::getInstance()->getActivityTypes())
      ->addWhere('source_record_id', 'IS NOT NULL')
      ->addWhere('contract_activity.contract_id', 'IS NULL')
      ->addWhere('id', '>', $fromId)
      ->addWhere('id', '<=', $toId)
      ->execute()
      ->indexBy('id')
      ->column('membership.id');

    foreach ($contractIds as $activityId => $contractId) {
      if (NULL === $contractId) {
        // Cascaded deletion is configured for field contract_activity.contract_id.
        $this->ctx->log->warning(E::ts(
          'Referenced contract does not exist, deleting referencing activity %1.',
          [1 => $activityId]
        ));
        Activity::delete(FALSE)
          ->addWhere('id', '=', $activityId)
          ->execute();
      }
      else {
        Activity::update(FALSE)
          ->addValue('contract_activity.contract_id', $contractId)
          ->addWhere('id', '=', $activityId)
          ->execute();
      }
    }

    return TRUE;
  }

  protected function ensureNoPaymentRequiredPaymentInstrument(): void {
    try {
      $currentNone = OptionValue::get(FALSE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('name', '=', 'None')
        ->setSelect(['id', 'value'])
        ->execute()
        ->single();
    }
    catch (\Throwable $e) {
      $currentNone = NULL;
    }

    try {
      $legacy = OptionValue::get(FALSE)
        ->addWhere('option_group_id.name', '=', 'payment_instrument')
        ->addWhere('name', '=', 'no_payment_required')
        ->setSelect(['id', 'value'])
        ->execute()
        ->single();
    }
    catch (\Throwable $e) {
      $legacy = NULL;
    }

    if ($legacy && !$currentNone) {
      OptionValue::update(FALSE)
        ->addWhere('id', '=', $legacy['id'])
        ->addValue('name', 'None')
        ->addValue('label', 'None')
        ->execute();
      CRM_Core_PseudoConstant::flush();
      return;
    }

    if ($legacy && $currentNone) {
      OptionValue::delete(FALSE)
        ->addWhere('id', '=', $legacy['id'])
        ->execute();
      CRM_Core_PseudoConstant::flush();
      return;
    }

    if (!$currentNone) {
      OptionValue::create(FALSE)
        ->addValue('option_group_id:name', 'payment_instrument')
        ->addValue('label', 'None')
        ->addValue('name', 'None')
        ->addValue('value', $this->findNextAvailablePaymentInstrumentValueByGroupName('payment_instrument'))
        ->addValue('is_active', 1)
        ->addValue('is_reserved', 0)
        ->addValue('weight', 99)
        ->execute();
      CRM_Core_PseudoConstant::flush();
    }
  }

  protected function findNextAvailablePaymentInstrumentValueByGroupName(string $groupName): int {
    $rows = OptionValue::get(FALSE)
      ->addWhere('option_group_id.name', '=', $groupName)
      ->setSelect(['value'])
      ->execute()
      ->getArrayCopy();
    $used = array_map('intval', array_column($rows, 'value'));
    $next = 1;
    while (in_array($next, $used, TRUE)) {
      $next++;
    }
    return $next;
  }

}
