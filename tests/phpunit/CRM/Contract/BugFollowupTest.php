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
        'start_date' => date("Y-m-d", strtotime("now -6 months")),
        'frequency_interval' => 12,
        'frequency_unit' => 'month',
    ],                                      true);
    $contract = $this->createNewContract([
        'membership_payment.membership_recurring_contribution' => $payment['id'],
        'contact_id' => $payment['contact_id']
     ]);

    // calculate next installment date, should be in roughly six months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime("now + 5 months"), "The next installment for this yearly contract should be in 6 months, since it started 6 months ago.");
    $this->assertTrue(strtotime($next_installment) < strtotime("now + 7 months"), "The next installment for this yearly contract should be in 6 months, since it started 6 months ago.");



    // test 2: new sepa mandate in the future
    $payment = $this->createPaymentContract([
            'start_date' => date("Y-m-d", strtotime("+6 month")),
            'frequency_interval' => 12,
            'frequency_unit' => 'month',
        ],                                  true);
    $contract = $this->createNewContract([
         'membership_payment.membership_recurring_contribution' => $payment['id'],
         'contact_id' => $payment['contact_id']
     ]);

    // calculate next installment date, earliest should be in 6 months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime("+5 month"), "The next installment for this yearly contract should be in 6 months, since it started 6 months ago.");
    $this->assertTrue(strtotime($next_installment) < strtotime("+7 month"), "The next installment for this yearly contract should be in 6 months, since it started 6 months ago.");


//    22398 6,- im Quartal nächster Einzug zum 15.02.23 -> 6,- im Halbjahr ab 15.12.22 x nächster Einzug am 15.02.23*

    // test 3: new sepa mandate in the future
    $payment = $this->createPaymentContract([
          'start_date' => date("Y-m-d", strtotime("now - 1 month")),
          'frequency_interval' => 3,
          'frequency_unit' => 'month',
      ],                                    true);
    $contract = $this->createNewContract([
         'membership_payment.membership_recurring_contribution' => $payment['id'],
         'contact_id' => $payment['contact_id']
     ]);

    // calculate next installment date, earliest should be in 2 months
    $next_installment = CRM_Contract_SepaLogic::getNextInstallmentDate($contract['membership_payment.membership_recurring_contribution']);
    $this->assertTrue(strtotime($next_installment) > strtotime("+1 month + 2 weeks"), "The next installment for this yearly contract should be in 2 months, since it started 1 month ago.");
    $this->assertTrue(strtotime($next_installment) < strtotime("+3 month - 2 weeks"), "The next installment for this yearly contract should be in 2 months, since it started 1 month ago.");
  }
}
