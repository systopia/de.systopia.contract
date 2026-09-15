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

use Civi\Core\Event\GenericHookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ContributionLinksSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return ['hook_civicrm_links' => 'modifyLinks'];
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function modifyLinks(GenericHookEvent $event): void {
    $objectId = (int) $event->objectId;
    /** @var array<int, array<string, mixed>> $links */
    $links = &$event->links;
    if ($event->op !== 'contribution.selector.row' || 0 === $objectId) {
      return;
    }

    // add a Contract link to contributions that are connected to memberships
    $contributionId = $objectId;
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
