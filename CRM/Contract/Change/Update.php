<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Contract\ContractChange\ActionMenuEntry;
use CRM_Contract_ExtensionUtil as E;

/**
 * "Update Membership" change
 */
class CRM_Contract_Change_Update extends CRM_Contract_Change_UpdateBase {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-pencil')
      ->setWeight(0);
  }

  public static function getActionName(): string {
    return 'update';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Updated';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-arrow-circle-o-up';
  }

  public static function getStartStatusList(): array {
    return ['New', 'Grace', 'Current'];
  }

  public static function getTitle(): string {
    return E::ts('Update Contract');
  }

}
