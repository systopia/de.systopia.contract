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

use Civi\Api4\Membership;
use Civi\Contract\ContractChange\ActionMenuAwareContractChangeTypeInterface;
use Civi\Contract\ContractChange\ContractChangeTypeContainer;
use Civi\Core\Event\GenericHookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MembershipLinksSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return ['hook_civicrm_links' => 'modifyLinks'];
  }

  public function __construct(
    private readonly ContractChangeTypeContainer $changeTypeContainer
  ) {}

  /**
   * @throws \CRM_Core_Exception
   */
  public function modifyLinks(GenericHookEvent $event): void {
    $objectId = (int) $event->objectId;
    /** @var array<int, array<string, mixed>> $links */
    $links = &$event->links;
    if ($event->objectName === 'Membership' && 0 !== $objectId) {
      // Custom links for memberships

      $this->removeObsoleteActions($links);

      /** @var string $statusName */
      $statusName = Membership::get(FALSE)
        ->addSelect('status_id:name')
        ->addWhere('id', '=', $objectId)
        ->execute()
        ->single()['status_id:name'];

      $this->addActions($statusName, $links);
    }
  }

  /**
   * @param array<int, array<string, mixed>> $links
   */
  private function addActions(string $statusName, array &$links): void {
    foreach ($this->changeTypeContainer->getClassesByActivityType() as $changeTypeClass) {
      if ($changeTypeClass instanceof ActionMenuAwareContractChangeTypeInterface) {
        if (in_array($statusName, $changeTypeClass::getStartStatusList(), TRUE)) {
          $menuEntry = $changeTypeClass::getActionMenuEntry();
          [$path, $query] = explode('?', str_replace('[id]', '%%id%%', $menuEntry->path), 2) + ['', ''];

          $links[] = [
            'name'  => $menuEntry->title,
            'url' => $path,
            'bit' => \CRM_Core_Action::UPDATE,
            'qs' => $query,
            'weight' => $menuEntry->weight,
          ];
        }
      }
    }
  }

  /**
   * @param array<int, array<string, mixed>> $links
   */
  private function removeObsoleteActions(array &$links): void {
    $obsoleteActions = [
      \CRM_Core_Action::RENEW,
      \CRM_Core_Action::FOLLOWUP,
      \CRM_Core_Action::DELETE,
      \CRM_Core_Action::UPDATE,
    ];
    foreach ($links as $key => $link) {
      if (in_array($link['bit'], $obsoleteActions, TRUE)) {
        unset($links[$key]);
      }
    }
  }

}
