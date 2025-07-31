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
 * Basic Contract Engine Tests
 *
 * @group headless
 */
class CRM_Contract_EngineStressTest extends CRM_Contract_ContractTestBase {

  public function setUp() : void {
    parent::setUp();
  }

  /**
   * Test execution of multiple updates and conflict handling
   */
  public function testMultiUpgrade(): void {
    $ITERATION_COUNT = 11;
    foreach ([1, 0] as $is_sepa) {
      // create a new contract
      $contract = $this->createNewContract(['is_sepa' => $is_sepa]);
      $scheduled_updates = $this->callAPISuccess(
        'Contract',
        'get_open_modification_counts',
        ['id' => $contract['id']]
      )['values'];
      static::assertEquals(0, $scheduled_updates['scheduled'], 'There should not be a scheduled change');

      // schedule a bunch of updates, one for each of the next $ITERATION_COUNT days
      for ($i = 1; $i <= $ITERATION_COUNT; $i++) {
        $update = [
          'membership_payment.membership_annual' => (1 * $i),
          'contract_updates.ch_payment_instrument' => CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR'),
        ];
        if (0 === $is_sepa) {
          // FIXME: if this is not a SEPA contract, we need to pass the bank account
          $update['membership_payment.from_ba'] = $this->getBankAccountID((int) $contract['contact_id']);
        }
        $this->modifyContract($contract['id'], 'update', date('YmdHis', strtotime("now + {$i} days")), $update);
      }

      // now... these should all be conflicting
      $scheduled_updates = $this->callAPISuccess(
        'Contract',
        'get_open_modification_counts',
        ['id' => $contract['id']]
      )['values'];
      static::assertEquals(
        $ITERATION_COUNT,
        $scheduled_updates['needs_review'],
        "There should be {$ITERATION_COUNT} updates that need review."
      );

      // cheekily set all of the 'needs review' ones to 'scheduled'
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_activity SET status_id = 1 WHERE source_record_id = {$contract['id']} AND status_id <> 2;"
      );

      // now... these should all be scheduled
      $scheduled_updates = $this->callAPISuccess(
        'Contract',
        'get_open_modification_counts',
        ['id' => $contract['id']]
      )['values'];
      static::assertEquals(
        $ITERATION_COUNT,
        $scheduled_updates['scheduled'],
        "There should be {$ITERATION_COUNT} update scheduled."
      );

      // run engine for 5 days from now (should execute 5 updates)
      $result = $this->runContractEngine($contract['id'], '+5 days');
      $contract_changed2 = $this->getContract($contract['id']);
      static::assertNotEquals($contract, $contract_changed2, 'This should have changed');

      // should have executed 5 updates and set the remaining to "needs review"
      $scheduled_updates = $this->callAPISuccess(
        'Contract',
        'get_open_modification_counts',
        ['id' => $contract['id']]
      )['values'];
      $expectedReviews = $ITERATION_COUNT - 5;
      static::assertEquals(
        $expectedReviews,
        $scheduled_updates['needs_review'],
        "There should be {$expectedReviews} update that need review."
      );
      static::assertEquals(0, $scheduled_updates['scheduled'], 'There should be no scheduled updates.');
    }
  }

}
