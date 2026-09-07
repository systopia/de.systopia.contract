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
 * "Revive Membership" change
 */
class CRM_Contract_Change_Revive extends CRM_Contract_Change_UpdateBase {

  public static function getActionMenuEntry(): ActionMenuEntry {
    return parent::getActionMenuEntry()
      ->setIcon('fa-refresh')
      ->setWeight(30);
  }

  public static function getActionName(): string {
    return 'revive';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Revived';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-play-circle-o';
  }

  public static function getStartStatusList(): array {
    return ['Cancelled'];
  }

  public static function getTitle(): string {
    return E::ts('Revive Contract');
  }

  /**
   * @inheritDoc
   */
  public function updateContract(array $updates): void {
    // Revive does all the same things as Upgrade, except it also removes end_date and sets status
    $updates['end_date'] = '';
    $updates['status_id:name'] = 'Current';
    parent::updateContract($updates);
  }

}
