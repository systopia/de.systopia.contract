<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2026 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

/**
 * Injects contract change actions (Update / Cancel / Pause / Resume / Revive)
 * into the SearchKit-driven membership tab on the contact summary page.
 *
 * CiviCRM 6 replaced the legacy hook_civicrm_links-rendered membership table
 * with a SearchKit display (Contact_Summary_Memberships_Active / _Inactive),
 * which does not consult hook_civicrm_links. This subscriber appends our
 * action links to that display at runtime.
 */
class CRM_Contract_SearchDisplayLinks {

  private const ACTIVE_DISPLAY = 'Contact_Summary_Memberships_Active';
  private const INACTIVE_DISPLAY = 'Contact_Summary_Memberships_Inactive';

  /**
   * Listener for civi.api.respond. Augments the result of
   * SearchDisplay.get for the two contact-summary membership displays.
   */
  public static function onApiRespond(\Civi\API\Event\RespondEvent $event): void {
    $request = $event->getApiRequest();
    if (!is_object($request) || ($request['version'] ?? NULL) != 4) {
      return;
    }
    if ($request->getEntityName() !== 'SearchDisplay' || $request->getActionName() !== 'get') {
      return;
    }

    $response = $event->getResponse();
    if (!$response) {
      return;
    }
    $changed = FALSE;
    foreach ($response as $i => $display) {
      $name = $display['name'] ?? '';
      if ($name !== self::ACTIVE_DISPLAY && $name !== self::INACTIVE_DISPLAY) {
        continue;
      }
      $links = $name === self::ACTIVE_DISPLAY
        ? self::getActiveDisplayLinks()
        : self::getInactiveDisplayLinks();

      foreach ($display['settings']['columns'] ?? [] as $j => $column) {
        if (($column['type'] ?? '') === 'menu' && isset($column['links']) && is_array($column['links'])) {
          $response[$i]['settings']['columns'][$j]['links'] = array_merge($column['links'], $links);
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $event->setResponse($response);
    }
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private static function getActiveDisplayLinks(): array {
    return [
      self::link('update', E::ts('Update Contract'), 'fa-pencil',
        ['status_id:name', 'IN', CRM_Contract_Change_Upgrade::getStartStatusList()]),
      self::link('pause', E::ts('Pause Contract'), 'fa-pause',
        ['status_id:name', 'IN', CRM_Contract_Change_Pause::getStartStatusList()]),
      self::link('resume', E::ts('Resume Contract'), 'fa-play',
        ['status_id:name', 'IN', CRM_Contract_Change_Resume::getStartStatusList()]),
      self::link('cancel', E::ts('Cancel Contract'), 'fa-times',
        ['status_id:name', 'IN', CRM_Contract_Change_Cancel::getStartStatusList()],
        'danger'),
    ];
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  private static function getInactiveDisplayLinks(): array {
    return [
      self::link('revive', E::ts('Revive Contract'), 'fa-rotate-left',
        ['status_id:name', 'IN', CRM_Contract_Change_Revive::getStartStatusList()]),
    ];
  }

  /**
   * @param array<int, mixed> $condition
   * @return array<string, mixed>
   */
  private static function link(string $action, string $text, string $icon, array $condition, string $style = 'default'): array {
    return [
      'path' => "civicrm/contract/modify?reset=1&id=[id]&modify_action={$action}",
      'icon' => $icon,
      'text' => $text,
      'style' => $style,
      'condition' => $condition,
      'task' => '',
      'entity' => '',
      'action' => '',
      'join' => '',
      'target' => '',
    ];
  }

}
