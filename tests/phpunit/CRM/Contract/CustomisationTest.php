<?php

use CRM_Contract_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;
use Civi\Contract\Event\RenderChangeSubjectEvent;

include_once 'ContractTestBase.php';

/**
 * Basic Contract Engine Tests
 *
 * @group headless
 */
class CRM_Contract_CustomisationTest extends CRM_Contract_ContractTestBase {

  public function setUp() {
    parent::setUp();
    Civi::dispatcher()->addListener(
        RenderChangeSubjectEvent::EVENT_NAME,
        ['CRM_Contract_CustomisationTest', 'renderSubjectTest1']);

  }

  /**
   * Test execution of multiple updates and conflict handling
   */
  public function testRenderSubjectCustomisation() {
    $contract = $this->createNewContract(['is_sepa' => 1]);
    $last_change = $this->getLastChangeActivity($contract['id']);
    $this->assertEquals('TEST-sign', $last_change['subject'], "The customisation hook failed.");

    // cancel contract
    $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
        'membership_cancellation.membership_cancel_reason' => 'Unknown'
    ]);
    $this->runContractEngine($contract['id'], '+2 days');
    $last_change = $this->getLastChangeActivity($contract['id']);
    $this->assertEquals('TEST-cancel', $last_change['subject'], "The customisation hook failed.");
  }

  /**
   * Render a custom change activity subject
   *
   * @param RenderChangeSubjectEvent $event
   *   the Contract Extension's render change subject event
   *
   * @see https://projekte.systopia.de/issues/18511#note-10
   */
  public static function renderSubjectTest1(RenderChangeSubjectEvent $event)
  {
    $event->setRenderedSubject("TEST-" . $event->getActivityAction());
  }
}
