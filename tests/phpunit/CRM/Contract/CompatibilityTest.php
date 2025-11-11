<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2025 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * Compatibility Tests: Make sure the engine refactoring is still compatible
 * with the old behavior where wanted
 *
 * @group headless
 *
 * @covers ::civicrm_api3_Contract_create
 * @covers ::civicrm_api3_Contract_modify
 * @covers ::civicrm_api3_Contract_process_scheduled_modifications
 */
class CRM_Contract_CompatibilityTest extends CRM_Contract_ContractTestBase {

  public function setUp() : void {
    parent::setUp();
    $this->setActivityFlavour('GP');
  }

  /**
   * Check if the subject of the change activity is as requested
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testUpdateActivitySubject() {
    // create a new contract
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '12.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'cycle_day'          => 25,
      'iban'               => 'DE89370400440532013000',
      'bic'                => 'GENODEM1GLS',
    ]);

    // modify contract
    $this->modifyContract($contract['id'], 'update', 'tomorrow', [
      'membership_payment.membership_annual' => '168.00',
      'membership_payment.cycle_day'         => '3',
      'contract_updates.ch_payment_instrument' => CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR'),
    ]);

    // get the resulting change activity
    $this->runContractEngine($contract['id'], '+2 days');
    $change_activity = $this->getLastChangeActivity($contract['id']);
    $this->assertNotEmpty($change_activity, 'There should be a change activity after the upgrade');

    if ($this->isExtensionActive('tazcontract')) {
      $this->assertStringContainsOtherString(
        'Anpassung',
        $change_activity['subject'],
        "Activity subject should contain 'Anpassung'"
      );
      $this->assertStringContainsOtherString(
        'erhöht',
        $change_activity['subject'],
        "Activity subject should contain 'erhöht'"
      );
    }
    else {
      $this->assertStringContainsOtherString(
        'Cycle day: 25 → 3',
        $change_activity['subject'],
        'Activity subject should contain the changed cycle day'
      );
      $this->assertStringContainsOtherString(
        'Annual amount: 144.00 → 168.00',
        $change_activity['subject'],
        'Activity subject should contain the changed amount'
      );
      $this->assertStringNotContainsOtherString(
        'DE89370400440532013000',
        $change_activity['subject'],
        'Activity subject should NOT contain the unchanged IBAN'
      );
      $this->assertStringNotContainsOtherString(
        'freq. 12 to 12',
        $change_activity['subject'],
        'Activity subject should NOT contain the unchanged frequency'
      );
    }
  }

  /**
   * Check if the subject of the change activity is as requested
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testCancelActivitySubject() {
    // create a new contract
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '12.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'cycle_day'          => 25,
      'iban'               => 'DE89370400440532013000',
      'bic'                => 'GENODEM1GLS',
    ]);

    // cancel contract
    $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown',
    ]);

    // get the resulting change activity
    $this->runContractEngine($contract['id'], '+2 days');
    $change_activity = $this->getLastChangeActivity($contract['id']);
    $this->assertNotEmpty($change_activity, 'There should be a change activity after the upgrade');
    $this->assertStringContainsOtherString(
      'Unknown',
      $change_activity['subject'],
      'Activity subject should contain the cancel reason'
    );
  }

  /**
   * Check if the system created activities are suppressed (if enabled)
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testSuppressSystemActivities() {
    $suppressed_types = CRM_Contract_Configuration::suppressSystemActivityTypes();
    if (!empty($suppressed_types)) {
      $contact_id = $this->createContactWithRandomEmail()['id'];

      // get the last activity (we don't care about the previous ones)
      $last_activity_id = $this->getLastActivityID([
        'activity_type_id'  => ['IN' => $suppressed_types],
        'target_contact_id' => $contact_id,
      ]);

      // create a new contract
      $contract = $this->createNewContract([
        'contact_id'         => $contact_id,
        'is_sepa'            => 1,
        'amount'             => '12.00',
        'frequency_unit'     => 'month',
        'frequency_interval' => '1',
        'cycle_day'          => 25,
        'iban'               => 'DE89370400440532013000',
        'bic'                => 'GENODEM1GLS',
      ]);

      // see if there has been an activity created AFTER the last one (if there was one)
      $next_activity_id = $this->getLastActivityID([
        'activity_type_id'  => ['IN' => $suppressed_types],
        'target_contact_id' => $contact_id,
      ], $last_activity_id);
      $this->assertEmpty(
        $next_activity_id,
        "A system activity was generated after contract creation event though it's supposed to be suppressed"
      );

      // modify contract
      $this->modifyContract($contract['id'], 'update', 'tomorrow', [
        'membership_payment.membership_annual' => '168.00',
        'membership_payment.cycle_day'         => '3',
      ]);
      $this->runContractEngine($contract['id'], '+2 days');

      // see if there has been an activity created AFTER the last one (if there was one)
      $next_activity_id = $this->getLastActivityID([
        'activity_type_id'  => ['IN' => $suppressed_types],
        'target_contact_id' => $contact_id,
      ], $last_activity_id);
      $this->assertEmpty(
        $next_activity_id,
        "A system activity was generated after contract update event though it's supposed to be suppressed"
      );

      // pause contract
      $this->modifyContract($contract['id'], 'pause', '+2 days');
      $this->runContractEngine($contract['id'], '+4 days');
      $next_activity_id = $this->getLastActivityID([
        'activity_type_id'  => ['IN' => $suppressed_types],
        'target_contact_id' => $contact_id,
      ], $last_activity_id);
      $this->assertEmpty(
        $next_activity_id,
        "A system activity was generated after contract pause event though it's supposed to be suppressed"
      );
    }
  }

  /**
   * Check if the Contract.modify can be called without a date
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testContractModifyWithoutDate() {
    // create a new contract
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '12.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'cycle_day'          => 25,
      'iban'               => 'DE89370400440532013000',
      'bic'                => 'GENODEM1GLS',
    ]);

    // try the cancel API
    $this->callAPISuccess('Contract', 'modify', [
      'id'                                               => $contract['id'],
      'modify_action'                                    => 'cancel',
      'medium_id'                                        => 1,
      'membership_cancellation.membership_cancel_reason' => 1,
    ]);
  }

  /**
   * Check if the payment links are generated correctly
   *
   * @see https://redmine.greenpeace.at/issues/1276#note-74
   */
  public function testGetPaymentLinks() {
    // create a new contract
    $contract = $this->createNewContract([
      'is_sepa'            => 1,
      'amount'             => '12.00',
      'frequency_unit'     => 'month',
      'frequency_interval' => '1',
      'cycle_day'          => 25,
      'iban'               => 'DE89370400440532013000',
      'bic'                => 'GENODEM1GLS',
    ]);

    // get the payment links
    $contract = $this->callAPISuccess('ContractPaymentLink', 'getactive', [
      'contract_id' => $contract['id'],
    ]);
  }

}
