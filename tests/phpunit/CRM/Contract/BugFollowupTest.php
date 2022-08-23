<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

include_once 'ContractTestBase.php';

/**
 * Bug reproduction and follow-up tests
 *
 * @group headless
 */
class CRM_Contract_BugFollowUpTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
  }

  /**
   * Follow-up to a bug, where the cancellation info (reason/date) is not passed to the respective custom fields
   *
   * @see https://projekte.systopia.de/issues/18706
   */
  public function testTicket18706() {
    // create a new contract
    $contract = $this->createNewContract(['is_sepa' => 1]);

    // schedule and update for tomorrow
    $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
        'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);

    // run engine see if anything changed
    $cancel_activity_before_execution = $this->getLastChangeActivity($contract['id']);
    $this->runContractEngine($contract['id'], 'now + 3 days');
    $cancel_activity_after_execution = $this->getLastChangeActivity($contract['id']);

    // reload the contract
    $contract_after_cancellation = $this->getContract($contract['id']);

    // things should not have changed
    $this->assertArrayHasKey('membership_cancellation.membership_cancel_reason', $contract_after_cancellation, "the cancel reason wasn't saved.");
    $this->assertNotEmpty($contract_after_cancellation['membership_cancellation.membership_cancel_reason'], "the cancel reason wasn't saved.");
    $this->assertArrayHasKey('membership_cancellation.membership_cancel_date', $contract_after_cancellation, "the cancel date wasn't saved.");
    $this->assertNotEmpty($contract_after_cancellation['membership_cancellation.membership_cancel_date'], "the cancel date wasn't saved.");
  }
}
