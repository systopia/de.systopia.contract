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
use Civi\Api4\OptionValue;

/**
 * Collection of upgrade steps.
 */
class CRM_Contract_Upgrader extends CRM_Extension_Upgrader_Base {

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
    return TRUE;
  }

  public function upgrade_1370() {
    $this->ctx->log->info('Applying update 1370');
    return TRUE;
  }

  public function upgrade_1390() {
    $this->ctx->log->info('Applying update 1390');
    $logging = new CRM_Logging_Schema();
    $logging->fixSchemaDifferences();
    return TRUE;
  }

  public function upgrade_1402() {
    $this->ctx->log->info('Applying updates for 14xx');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_contact_channel.json'));
    $customData->syncOptionGroup(E::path('resources/option_group_order_type.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_general.json'));
    return TRUE;
  }

  public function upgrade_1403() {
    $this->ctx->log->info('Applying updates for 14xx');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
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
    return TRUE;
  }

  public function upgrade_1502() {
    $this->ctx->log->info('Hide/filter activity types');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    return TRUE;
  }

  public function upgrade_1503() {
    $this->ctx->log->info('Update translations');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncCustomGroup(E::path('resources/custom_group_membership_payment.json'));
    $customData->syncCustomGroup(E::path('resources/custom_group_contract_updates.json'));
    return TRUE;
  }

  public function upgrade_2000() {
    $this->ctx->log->info('Adjust filters for contract actions');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncOptionGroup(E::path('resources/option_group_activity_types.json'));
    return TRUE;
  }

  public function upgrade_2001() {
    $this->ctx->log->info('Update contract types');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncEntities(E::path('resources/option_group_activity_types.json'));
    return TRUE;
  }

  public function upgrade_2002() {
    $this->ctx->log->info('Delete dialoger field on contract');
    $customData = new CRM_Contract_CustomData(E::LONG_NAME);
    $customData->syncEntities(E::path('resources/custom_group_membership_general.json'));
    return TRUE;
  }

  public function upgrade_2003() {
    $this->ctx->log->info('Add "No Payment required" payment instrument.');
    $this->ensureNoPaymentRequiredPaymentInstrument();
    return TRUE;
  }

  public function upgrade_2004() {
    $this->ctx->log->info('Add ContractMembershipRelation entity schema.');
    E::schema()->createEntityTable('schema/ContractMembershipRelation.entityType.php');
    return TRUE;
  }

  protected function ensureNoPaymentRequiredPaymentInstrument() {
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

  protected function findNextAvailablePaymentInstrumentValueByGroupName(string $groupName) {
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
