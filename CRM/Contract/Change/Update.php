<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2019 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * "Update Membership" change
 */
class CRM_Contract_Change_Update extends CRM_Contract_Change_UpdateBase {

  public static function getActionName(): string {
    return 'update';
  }

  public static function getActivityTypeName(): string {
    return 'Contract_Updated';
  }

  public static function getActivityTypeIcon(): string {
    return 'fa-arrow-circle-o-up';
  }

  public static function getTitle(): string {
    return E::ts('Update Contract');
  }

  /**
   * @inheritDoc
   */
  public static function getStartStatusList(): array {
    return ['New', 'Grace', 'Current'];
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param array<int, array<string, mixed>> $links currently given links
   * @param string $current_status_name membership status as a string
   * @param array<string, mixed> $membership_data all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(
    array &$links,
    string $current_status_name,
    array $membership_data
  ): void {
    if (in_array($current_status_name, self::getStartStatusList(), TRUE)) {
      $links[] = [
        'name'  => E::ts('Update'),
        'title' => self::getTitle(),
        'url'   => 'civicrm/contract/modify',
        'bit'   => CRM_Core_Action::UPDATE,
        'qs'    => 'modify_action=update&id=%%id%%',
      ];
    }
  }

}
