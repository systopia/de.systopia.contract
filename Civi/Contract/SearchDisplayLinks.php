<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2026 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract;

use Civi\API\Event\RespondEvent;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use CRM_Contract_ExtensionUtil as E;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects contract change actions (Update / Cancel / Pause / Resume / Revive)
 * into the SearchKit-driven membership tab on the contact summary page.
 *
 * CiviCRM 6 replaced the legacy hook_civicrm_links-rendered membership table
 * with a SearchKit display (Contact_Summary_Memberships_Active / _Inactive),
 * which does not consult hook_civicrm_links. This subscriber appends our
 * action links to that display at runtime.
 */
class SearchDisplayLinks implements EventSubscriberInterface {

  private const ACTIVE_DISPLAY = 'Contact_Summary_Memberships_Active';
  private const INACTIVE_DISPLAY = 'Contact_Summary_Memberships_Inactive';

  /**
   * @inheritDoc
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array {
    return [
      'civi.api.respond' => 'onApiRespond',
    ];
  }

  /**
   * Listener for civi.api.respond. Augments the result of
   * SearchDisplay.get for the two contact-summary membership displays.
   */
  public function onApiRespond(RespondEvent $event): void {
    $request = $event->getApiRequest();
    if (!$request instanceof AbstractAction) {
      return;
    }
    if ($request->getEntityName() !== 'SearchDisplay' || $request->getActionName() !== 'get') {
      return;
    }

    $response = $event->getResponse();
    if (!$response instanceof Result) {
      return;
    }

    $changed = FALSE;
    foreach ($response as $index => $display) {
      if (!is_array($display)) {
        continue;
      }
      $modified = self::applyLinks($display);
      if (NULL !== $modified) {
        $response[$index] = $modified;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $event->setResponse($response);
    }
  }

  /**
   * Appends the contract action links to a single SearchDisplay definition.
   *
   * @param array<mixed> $display
   *
   * @return array<mixed>|null
   *   The modified display, or NULL if it is not a target display or has no
   *   menu column to add links to.
   */
  private static function applyLinks(array $display): ?array {
    $name = $display['name'] ?? NULL;
    $links = is_string($name) ? self::linksForDisplay($name) : NULL;
    if (NULL === $links) {
      return NULL;
    }

    $settings = $display['settings'] ?? NULL;
    if (!is_array($settings)) {
      return NULL;
    }
    $columns = $settings['columns'] ?? NULL;
    if (!is_array($columns)) {
      return NULL;
    }

    $merged = self::addLinksToMenuColumns($columns, $links);
    if (NULL === $merged) {
      return NULL;
    }

    $settings['columns'] = $merged;
    $display['settings'] = $settings;

    return $display;
  }

  /**
   * @return list<array<string, mixed>>|null
   *   The action links for the given display, or NULL if it is not a display
   *   we augment.
   */
  private static function linksForDisplay(string $name): ?array {
    return match ($name) {
      self::ACTIVE_DISPLAY => self::getActiveDisplayLinks(),
      self::INACTIVE_DISPLAY => self::getInactiveDisplayLinks(),
      default => NULL,
    };
  }

  /**
   * Merges the given links into every "menu" type column.
   *
   * @param array<mixed> $columns
   * @param list<array<string, mixed>> $links
   *
   * @return array<mixed>|null
   *   The columns with links merged in, or NULL if no menu column was found.
   */
  private static function addLinksToMenuColumns(array $columns, array $links): ?array {
    $changed = FALSE;
    foreach ($columns as $index => $column) {
      if (!is_array($column) || ($column['type'] ?? NULL) !== 'menu') {
        continue;
      }
      $existing = $column['links'] ?? NULL;
      if (!is_array($existing)) {
        continue;
      }
      $column['links'] = array_merge($existing, $links);
      $columns[$index] = $column;
      $changed = TRUE;
    }

    return $changed ? $columns : NULL;
  }

  /**
   * @return list<array<string, mixed>>
   */
  private static function getActiveDisplayLinks(): array {
    return [
      self::link('update', E::ts('Update Contract'), 'fa-pencil',
        ['status_id:name', 'IN', \CRM_Contract_Change_Upgrade::getStartStatusList()]),
      self::link('pause', E::ts('Pause Contract'), 'fa-pause',
        ['status_id:name', 'IN', \CRM_Contract_Change_Pause::getStartStatusList()]),
      self::link('resume', E::ts('Resume Contract'), 'fa-play',
        ['status_id:name', 'IN', \CRM_Contract_Change_Resume::getStartStatusList()]),
      self::link('cancel', E::ts('Cancel Contract'), 'fa-times',
        ['status_id:name', 'IN', \CRM_Contract_Change_Cancel::getStartStatusList()],
        'danger'),
    ];
  }

  /**
   * @return list<array<string, mixed>>
   */
  private static function getInactiveDisplayLinks(): array {
    return [
      self::link('revive', E::ts('Revive Contract'), 'fa-rotate-left',
        ['status_id:name', 'IN', \CRM_Contract_Change_Revive::getStartStatusList()]),
    ];
  }

  /**
   * @param array<int, mixed> $condition
   *
   * @return array<string, mixed>
   */
  private static function link(
    string $action,
    string $text,
    string $icon,
    array $condition,
    string $style = 'default'
  ): array {
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
