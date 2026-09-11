<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Contract\EventSubscriber;

use Civi\API\Event\RespondEvent;
use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;
use Civi\Api4\MembershipStatus;
use Civi\Contract\ContractChange\ActionMenuAwareContractChangeTypeInterface;
use Civi\Contract\ContractChange\ContractChangeTypeContainer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Injects contract change actions into the SearchKit-driven membership tab on
 * the contact summary page.
 *
 * CiviCRM 6 replaced the legacy hook_civicrm_links-rendered membership table
 * with a SearchKit display (Contact_Summary_Memberships_Active / _Inactive),
 * which does not consult hook_civicrm_links. This subscriber appends our
 * action links to that display at runtime.
 */
class MembershipSearchDisplayLinksSubscriber implements EventSubscriberInterface {

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

    foreach ($response as $index => $display) {
      if (!is_array($display)) {
        continue;
      }

      $modifiedDisplay = self::applyLinks($display);
      if (NULL !== $modifiedDisplay) {
        $response[$index] = $modifiedDisplay;
      }
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
      self::ACTIVE_DISPLAY => self::getDisplayLinks(TRUE),
      self::INACTIVE_DISPLAY => self::getDisplayLinks(FALSE),
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
   *
   * @throws \CRM_Core_Exception
   */
  private static function getDisplayLinks(bool $active): array {
    $statusNames = MembershipStatus::get(FALSE)
      ->addSelect('name')
      ->addWhere('is_current_member', '=', $active)
      ->execute()
      ->column('name');

    $linksByWeight = [];

    foreach (ContractChangeTypeContainer::getInstance()->getClassesByActivityType() as $changeTypeClass) {
      if ($changeTypeClass instanceof ActionMenuAwareContractChangeTypeInterface) {
        if ([] !== array_intersect($statusNames, $changeTypeClass::getStartStatusList())) {
          $menuEntry = $changeTypeClass::getActionMenuEntry();
          $linksByWeight[$menuEntry->weight][] = [
            'path' => $menuEntry->path,
            'icon' => $menuEntry->icon,
            'text' => $menuEntry->title,
            'style' => $menuEntry->style,
            'condition' => ['status_id:name', 'IN', $changeTypeClass::getStartStatusList()],
            'task' => '',
            'entity' => '',
            'action' => '',
            'join' => '',
            'target' => '',
          ];
        }
      }
    }

    ksort($linksByWeight);

    return array_values(
      array_reduce($linksByWeight, fn ($links, $weightedLinks) => array_merge($links, $weightedLinks), [])
    );
  }

}
