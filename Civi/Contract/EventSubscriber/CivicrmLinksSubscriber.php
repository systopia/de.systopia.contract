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
use Civi\Contract\ContractChange\ContractChangeTypeContainer;
use Civi\Core\Event\GenericHookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CivicrmLinksSubscriber implements EventSubscriberInterface {

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
    if ($event->objectName === 'Membership') {
      // Custom links for memberships
      if (0 !== $objectId) {
        /** @var array<string, mixed> $membershipData */
        $membershipData = Membership::get(FALSE)
          ->addSelect('*', 'status_id:name')
          ->addWhere('id', '=', $objectId)
          ->execute()
          ->single();

        // alter links
        $this->modifyMembershipLinks($membershipData, $links);
      }
    }
    elseif ($event->op === 'contribution.selector.row') {
      // add a Contract link to contributions that are connected to memberships
      $contributionId = $objectId;
      if (0 !== $contributionId) {
        // add 'view contract' link
        $membershipId = (int) \CRM_Core_DAO::singleValueQuery(
          "SELECT membership_id FROM civicrm_membership_payment WHERE contribution_id = {$contributionId} LIMIT 1"
        );
        if (0 !== $membershipId) {
          $contactId = (int) \CRM_Core_DAO::singleValueQuery(
            "SELECT contact_id FROM civicrm_membership WHERE id = {$membershipId} LIMIT 1"
          );
          if (0 !== $contactId) {
            $links[] = [
              'name'  => 'Contract',
              'title' => 'View Contract',
              'url'   => 'civicrm/contact/view/membership',
              'qs'    => "reset=1&id={$membershipId}&cid={$contactId}&action=view",
            ];
          }
        }
      }
    }
  }

  /**
   * @param array<string, mixed> $membershipData
   * @param array<int, array<string, mixed>> $links
   */
  private function modifyMembershipLinks(array $membershipData, array &$links): void {
    // first remove the default ones that shouldn't be used anymore
    $obsolete_actions = [
      \CRM_Core_Action::RENEW,
      \CRM_Core_Action::FOLLOWUP,
      \CRM_Core_Action::DELETE,
      \CRM_Core_Action::UPDATE,
    ];
    foreach ($links as $key => $link) {
      if (in_array($link['bit'], $obsolete_actions, TRUE)) {
        unset($links[$key]);
      }
    }

    // add the replacement actions
    $statusName = $membershipData['status_id:name'];
    foreach ($this->changeTypeContainer->getClassesByActivityType() as $change_class) {
      if (method_exists($change_class, 'modifyMembershipActionLinks')) {
        $change_class::modifyMembershipActionLinks($links, $statusName, $membershipData);
      }
    }
  }

}
