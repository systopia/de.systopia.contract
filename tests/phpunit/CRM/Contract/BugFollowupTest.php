<?php

use CRM_Contract_ExtensionUtil as E;

include_once 'ContractTestBase.php';

/**
 * Bug reproduction and follow-up tests
 *
 * @group headless
 */
class CRM_Contract_BugFollowUpTest extends CRM_Contract_ContractTestBase {

  public function setUp() : void {
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
      'membership_cancellation.membership_cancel_reason' => 'Unknown',
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

  /**
   * Follow-up to a bug, where in some circumstances the next contribution date
   *  is not calculated correctly
   *
   * @see https://github.com/systopia/de.systopia.contract/issues/72
   * @see https://projekte.systopia.de/issues/19651
   */
  public function testTicket19651() {
    // test 1: new sepa mandate, no contribution yet
    $payment = $this->createPaymentContract([
      'start_date' => date('Y-m-d', strtotime('now -6 months')),
      'frequency_interval' => 12,
      'frequency_unit' => 'month',
    ], TRUE);
    $contract = $this->createNewContract([
      'membership_payment.membership_recurring_contribution' => $payment['id'],
      'contact_id' => $payment['contact_id'],
    ]);

    // calculate next installment date, should be in roughly six months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime('now + 5 months'), 'The next installment for this yearly contract should be in 6 months, since it started 6 months ago.');
    $this->assertTrue(strtotime($next_installment) < strtotime('now + 7 months'), 'The next installment for this yearly contract should be in 6 months, since it started 6 months ago.');

    // test 2: new sepa mandate in the future
    $payment = $this->createPaymentContract([
      'start_date' => date('Y-m-d', strtotime('+6 month')),
      'frequency_interval' => 12,
      'frequency_unit' => 'month',
    ], TRUE);
    $contract = $this->createNewContract([
      'membership_payment.membership_recurring_contribution' => $payment['id'],
      'contact_id' => $payment['contact_id'],
    ]);

    // calculate next installment date, earliest should be in 6 months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime('+5 month'), 'The next installment for this yearly contract should be in 6 months, since it started 6 months ago.');
    $this->assertTrue(strtotime($next_installment) < strtotime('+7 month'), 'The next installment for this yearly contract should be in 6 months, since it started 6 months ago.');

    // test 3: new sepa mandate in the future
    $payment = $this->createPaymentContract([
      'start_date' => date('Y-m-d', strtotime('now - 1 month')),
      'frequency_interval' => 3,
      'frequency_unit' => 'month',
    ], TRUE);
    $contract = $this->createNewContract([
      'membership_payment.membership_recurring_contribution' => $payment['id'],
      'contact_id' => $payment['contact_id'],
    ]);

    // calculate next installment date, earliest should be in 2 months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime('+1 month + 2 weeks'), 'The next installment for this yearly contract should be in 2 months, since it started 1 month ago.');
    $this->assertTrue(strtotime($next_installment) < strtotime('+3 month'), 'The next installment for this yearly contract should be in 2 months, since it started 1 month ago.');
  }

  /**
   * Follow-up to an improvement, where "yesterday's" modifications will still be processed within
   *  a configurable grace period
   *
   * @see https://github.com/systopia/de.systopia.contract/issues/76
   * @see https://projekte.systopia.de/issues/20444
   */
  public function testTicket20444() {
    // test 1: new sepa mandate, no contribution yet
    $payment = $this->createPaymentContract([
      'start_date' => date('Y-m-d', strtotime('now -1 hour')),
      'frequency_interval' => 1,
      'frequency_unit' => 'month',
    ], TRUE);
    $contract = $this->createNewContract([
      'membership_payment.membership_recurring_contribution' => $payment['id'],
      'contact_id' => $payment['contact_id'],
    ]);

    // now: schedule an amount change, but this would fail
    $this->callAPIFailure('Contract', 'modify', [
      'id' => $contract['id'],
      'modify_action' => 'update',
      'date' => date('Y-m-d', strtotime('yesterday')),
    ]);

    // now: set the adjustment range and try again
    Civi::settings()->set('date_adjustment', '1 day');
    $this->callAPISuccess('Contract', 'modify', [
      'id' => $contract['id'],
      'modify_action' => 'update',
      'date' => date('Y-m-d', strtotime('-23 hours')),
    ]);
  }

  /**
   * Follow-up to an error, where there is a reduction from "1.800,00 EUR" to "480,00 EUR",
   *  but the activity records an _increase_ of "478,20 EUR", due to an issue with the decimal pointer
   *
   * @see https://projekte.systopia.de/issues/23397
   */
  public function testTicket23397() {
    // test 1: new sepa mandate, no contribution yet
    $payment = $this->createPaymentContract([
      'start_date' => date('Y-m-d', strtotime('now -2 day')),
      'amount' => '1800.00',
      'frequency_unit' => 'month',
      'frequency_interval' => 12,
    ], TRUE);
    $contract = $this->createNewContract([
      'membership_payment.membership_recurring_contribution' => $payment['id'],
      'contact_id' => $payment['contact_id'],
    ]);
    $initial_change_activity = $this->getLastChangeActivity($contract['id']);

    // modify contract and check again
    $this->modifyContract($contract['id'], 'update', 'now', [
      'membership_payment.membership_annual' => '480.00',
    ]);
    $upgrade_change_type = CRM_Contract_Change::getActivityIdForClass('CRM_Contract_Change_Upgrade');
    $change_activity = $this->getLastChangeActivity($contract['id'], [$upgrade_change_type]);

    // reload contract
    $updated_contract = $this->getContract($contract['id']);
    $this->assertNotEmpty($change_activity, 'There should be a change activity.');
    $this->assertNotEmpty($change_activity['subject'], 'There should be a change activity subject.');
    if ($this->isExtensionActive('tazcontract')) {
      $this->assertStringContainsOtherString('1,320.00', $change_activity['subject']);
    }
    else {
      $this->assertStringContainsOtherString('amt. to 480.00', $change_activity['subject']);
    }
  }

}
