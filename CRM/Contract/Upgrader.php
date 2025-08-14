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

use CRM_Contract_ExtensionUtil as E;
use Civi\Api4\OptionGroup;
use Civi\Api4\OptionValue;

/**
 * Collection of upgrade steps.
 */
class CRM_Contract_Upgrader extends CRM_Extension_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are

  /**
   * upgrade tasks. They are executed in order (like Drupal's hook_update_N).
   */
  public function install() {
    $this->executeSqlFile('sql/contract.sql');
    $this->ensureNoPaymentRequiredPaymentInstrument();
  }

  public function enable() {
    require_once 'CRM/Contract/CustomData.php';
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_contact_channel.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_contract_cancel_reason.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_contract_cancel_reason.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_payment_frequency.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_status.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_cancellation.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_cancellation.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_general.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_order_type.json'));
    $customData->syncEntities(E::path('resources/entities_membership_status.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
  }

  public function postInstall() {
  }

  public function uninstall() {}

  /**
   * Add custom field "defer_payment_start"
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1360() {
    $this->ctx->log->info('Applying update 1360');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1370() {
    $this->ctx->log->info('Applying update 1370');
    $this->executeSqlFile('sql/contract.sql');
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1390() {
    $this->ctx->log->info('Applying update 1390');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1402() {
    $this->ctx->log->info('Applying updates for 14xx');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_contact_channel.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_order_type.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_general.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1403() {
    $this->ctx->log->info('Applying updates for 14xx');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_contract_updates.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_membership_payment.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1501() {
    $this->ctx->log->info('Applying localisation');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_contact_channel.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_contract_cancel_reason.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_payment_frequency.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_activity_status.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_cancellation.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_cancellation.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_general.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_order_type.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1502() {
    $this->ctx->log->info('Hide/filter activity types');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_1503() {
    $this->ctx->log->info('Update translations');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2000() {
    $this->ctx->log->info('Adjust filters for contract actions');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2001() {
    $this->ctx->log->info('Update contract types');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncEntities(E::path('resources/option_group_activity_types.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2002() {
    $this->ctx->log->info('Delete dialoger field on contract');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncEntities(E::path('resources/custom_group_membership_general.json'));
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2003() {
    $this->ctx->log->info('Add "No Payment required" payment instrument.');
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  protected function ensureNoPaymentRequiredPaymentInstrument() {
    try {
      $og = OptionGroup::get(FALSE)
        ->addWhere('name', '=', 'payment_instrument')
        ->setSelect(['id'])
        ->setLimit(1)
        ->execute()
        ->first();
    }
    catch (\Throwable $e) {
      return;
    }
    if (!$og || empty($og['id'])) {
      return;
    }
    $gid = (int) $og['id'];

    $currentNone = OptionValue::get(FALSE)
      ->addWhere('option_group_id', '=', $gid)
      ->addWhere('name', '=', 'None')
      ->setSelect(['id', 'value'])
      ->setLimit(1)
      ->execute()
      ->first();

    $legacy = OptionValue::get(FALSE)
      ->addWhere('option_group_id', '=', $gid)
      ->addWhere('name', '=', 'no_payment_required')
      ->setSelect(['id', 'value'])
      ->setLimit(1)
      ->execute()
      ->first();

    if ($legacy && !$currentNone) {
      OptionValue::update(FALSE)
        ->addWhere('id', '=', $legacy['id'])
        ->addValue('name', 'None')
        ->addValue('label', 'No payment required')
        ->execute()
        ->single();
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
        ->addValue('option_group_id', $gid)
        ->addValue('label', 'No payment required')
        ->addValue('name', 'None')
        ->addValue('value', $this->findNextAvailablePaymentInstrumentValue($gid))
        ->addValue('is_active', 1)
        ->addValue('is_reserved', 0)
        ->addValue('weight', 99)
        ->execute()
        ->single();
      CRM_Core_PseudoConstant::flush();
    }
  }

  protected function findNextAvailablePaymentInstrumentValue($optionGroupId) {
    $rows = OptionValue::get(FALSE)
      ->addWhere('option_group_id', '=', (int) $optionGroupId)
      ->setSelect(['value'])
      ->setLimit(0)
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
