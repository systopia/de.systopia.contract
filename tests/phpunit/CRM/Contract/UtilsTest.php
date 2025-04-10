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
 * Test utility functions
 *
 * @group headless
 */
class CRM_Contract_UtilsTest extends CRM_Contract_ContractTestBase {

  public function testStripNonContractActivityCustomFields(): void {
    $fields = CRM_Contract_CustomData::getCustomFieldsForGroups(['contract_cancellation', 'contract_updates']);
    $activityData = [
      'id'                         => 1,
      'activity_date_time'         => '20200101000000',
      'custom_' . $fields[0]['id'] => 'foo',
      'custom_9997'                => 'bar',
      'custom_9998_1'              => 'baz',
    ];
    CRM_Contract_Utils::stripNonContractActivityCustomFields($activityData);
    $this->assertArraysEqual([
      'id'                         => 1,
      'activity_date_time'         => '20200101000000',
      'custom_' . $fields[0]['id'] => 'foo',
    ],
      $activityData
    );
  }

}
