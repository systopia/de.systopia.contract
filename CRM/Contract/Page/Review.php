<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use Civi\Api4\Activity;
use Civi\Api4\Contact;
use Civi\Api4\ContributionRecur;
use Civi\Api4\MembershipType;
use Civi\Contract\Change\ContractChangeTypeContainer;
use Civi\Contract\Event\AdjustContractReviewEvent;
use Civi\Contract\Event\DisplayChangeTitle as DisplayChangeTitle;

class CRM_Contract_Page_Review extends CRM_Core_Page {

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function run(): void {
  // phpcs:enable
    // get the adjustments
    $adjustments = AdjustContractReviewEvent::getContractReviewAdjustments();

    if (!$id = CRM_Utils_Request::retrieve('id', 'Positive')) {
      throw new RuntimeException('Missing a valid contract ID');
    }

    // get contract currency from currently active recurring contribution
    // TODO: make currency changeable/store it with the contract update
    $membership = civicrm_api3('Membership', 'getsingle', [
      'id' => CRM_Utils_Request::retrieve('id', 'Positive'),
    ]);
    $this->assign('currency', civicrm_api3('ContributionRecur', 'getvalue', [
      'id' => $membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')],
      'return' => 'currency',
    ]));

    /** @phpstan-var array<int, array<string, mixed>> $activities */
    $activities = Activity::get(FALSE)
      ->addSelect(
        'activity_date_time',
        'status_id',
        'status_id:name',
        'status_id:label',
        'activity_type_id',
        'target_contact_id',
        'source_contact_id',
        'details',
        'campaign_id',
        'campaign_id.title',
        'medium_id:label',
        'contract_cancellation.contact_history_cancel_reason:label',
        'contract_payment_suspended.contribution_recur_id',
        'contract_payment_suspended.reason:label',
        'contract_updates.*',
        'contract_updates.ch_frequency:label',
      )
      ->addWhere('contract_activity.contract_id', '=', $id)
      ->addWhere('status_id:name', 'NOT IN', ['Cancelled'])
      ->addWhere('activity_type_id:name', 'IN', ContractChangeTypeContainer::getInstance()->getActivityTypes())
      ->addOrderBy('activity_date_time', 'DESC')
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    // Note: Selecting "source_contact_id.display_name" doesn't work so we use an extra call.
    /** @var array<int, int> $contactIds */
    $contactIds = array_column($activities, 'source_contact_id', 'source_contact_id');
    $contacts = Contact::get(FALSE)
      ->addSelect('id', 'display_name')
      ->addWhere('id', 'IN', $contactIds)
      ->execute()
      ->indexBy('id')
      ->column('display_name');
    $this->assign('contacts', $contacts);

    foreach ($activities as $activityId => $activity) {
      $activities[$activityId] = $this->adjustActivity($activity);
    }

    $this->assign('activities', $activities);

    $membershipTypes = MembershipType::get(FALSE)
      ->addSelect('id', 'title')
      ->execute()
      ->indexBy('id')
      ->column('title');
    $this->assign('membershipTypes', $membershipTypes);

    // hide some columns
    $this->assign('hide_columns', $adjustments->getHiddenColumnIndices());

    parent::run();
  }

  /**
   * @param array<string, mixed> $activity
   *
   * @return array<string, mixed>
   *
   * @throws \CRM_Core_Exception
   */
  private function adjustActivity(array $activity): array {
    $activity['reason_label'] = $activity['contract_payment_suspended.reason:label']
      ?? $activity['contract_cancellation.contact_history_cancel_reason:label']
      ?? NULL;

    foreach ($activity as $fieldName => $field) {
      $newFieldName = str_replace(['.', ':'], '_', $fieldName);
      if ($newFieldName !== $fieldName) {
        unset($activity[$fieldName]);
        $activity[$newFieldName] = $field;
      }
    }
    if (
      isset($activity['contract_updates_ch_recurring_contribution'])
      || isset($activity['contract_payment_suspended_contribution_recur_id'])
    ) {
      $contributionRecurId = $activity['recurring_contribution_id'] =
        $activity['contract_updates_ch_recurring_contribution']
        ?? $activity['contract_payment_suspended_contribution_recur_id'];

      /** @var array{contact_id: int, "payment_instrument_id:label": string} $contributionRecur */
      $contributionRecur = ContributionRecur::get(FALSE)
        ->addSelect('contact_id', 'payment_instrument_id:label')
        ->addWhere('id', '=', $contributionRecurId)
        ->execute()
        ->single();
      $activity['payment_instrument_id_label'] = $contributionRecur['payment_instrument_id:label'];
      $activity['recurring_contribution_contact_id'] = $contributionRecur['contact_id'];
    }
    if (
      isset($activity['contract_updates_ch_annual'])
      && isset($activity['contract_updates_ch_frequency'])
      && $activity['contract_updates_ch_annual'] > 0
      && $activity['contract_updates_ch_frequency'] > 0
    ) {
      $activity['contract_updates_ch_amount'] = CRM_Contract_SepaLogic::formatMoney(
          $activity['contract_updates_ch_annual']
        ) / $activity['contract_updates_ch_frequency'];
      $activity['contract_updates_ch_amount'] = CRM_Contract_SepaLogic::formatMoney(
        $activity['contract_updates_ch_amount']
      );
    }

    // add title/hover title
    $display_titles = DisplayChangeTitle::renderDisplayChangeTitleAndHoverText(
      $activity['activity_type_id'], $activity['id']);
    $activity['display_title'] = $display_titles->getDisplayTitle();
    $activity['display_hover_title'] = $display_titles->getDisplayHover();

    return $activity;
  }

}
