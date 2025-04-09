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
use Civi\Contract\Event\RenderChangeSubjectEventAbstract;

/**
 * Basic Contract Engine Tests
 *
 * @group headless
 */
class CRM_Contract_CustomisationTest extends CRM_Contract_ContractTestBase {

  public function setUp() : void {
    parent::setUp();
    Civi::dispatcher()->addListener(
        RenderChangeSubjectEventAbstract::EVENT_NAME,
        ['CRM_Contract_CustomisationTest', 'renderSubjectTest1']);

  }

  /**
   * Test execution of multiple updates and conflict handling
   */
  public function testRenderSubjectCustomisation() {
    $contract = $this->createNewContract(['is_sepa' => 1]);
    $last_change = $this->getLastChangeActivity($contract['id']);
    $this->assertEquals('TEST-sign', $last_change['subject'], 'The customisation hook failed.');

    // cancel contract
    $this->modifyContract($contract['id'], 'cancel', 'tomorrow', [
      'membership_cancellation.membership_cancel_reason' => 'Unknown',
    ]);
    $this->runContractEngine($contract['id'], '+2 days');
    $last_change = $this->getLastChangeActivity($contract['id']);
    $this->assertEquals('TEST-cancel', $last_change['subject'], 'The customisation hook failed.');
  }

  /**
   * Render a custom change activity subject
   *
   * @param \Civi\Contract\Event\RenderChangeSubjectEventAbstract $event
   *   the Contract Extension's render change subject event
   *
   * @see https://projekte.systopia.de/issues/18511#note-10
   */
  public static function renderSubjectTest1(RenderChangeSubjectEventAbstract $event) {
    $event->setRenderedSubject('TEST-' . $event->getActivityAction());
  }

}
