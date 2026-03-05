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

use Civi\Contract\Event\DisplayChangeTitle as DisplayChangeTitle;

class CRM_Contract_Page_Review extends CRM_Core_Page {

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function run() {
  // phpcs:enable
    // get the adjustments
    $adjustments = \Civi\Contract\Event\AdjustContractReviewEvent::getContractReviewAdjustments();

    if (!$id = CRM_Utils_Request::retrieve('id', 'Positive')) {
      throw new \RuntimeException('Missing a valid contract ID');
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
    $activities = \Civi\Api4\Activity::get(FALSE)
      ->addSelect(
        'activity_date_time',
        'status_id',
        'activity_type_id',
        'target_contact_id',
        'source_contact_id',
        'details',
        'campaign_id',
        'medium_id',
        'contract_cancellation.*',
        'contract_updates.*',
      )
      ->addWhere('contract_activity.contract_id', '=', $id)
      ->addWhere('status_id', 'NOT IN', ['Cancelled'])
      ->addWhere('activity_type_id', 'IN', CRM_Contract_Change::getActivityTypeIds())
      ->addOrderBy('activity_date_time', 'DESC')
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    // Friendlify custom field names
    CRM_Contract_Utils::warmCustomFieldCache();
    $customFieldIndex = array_flip(CRM_Contract_Utils::$customFieldCache);
    $customFieldIndex = str_replace('.', '_', $customFieldIndex);

    // To collect the campaign ids that we need to get the names of
    $campaigns = [];
    $contacts = [];
    $cancelReasons = [];

    // todo: refactor for better performance
    foreach ($activities as $activityId => $activity) {
      foreach ($activity as $fieldName => $field) {
        $newFieldName = str_replace('.', '_', $fieldName);
        if ($newFieldName !== $fieldName) {
          unset($activities[$activityId][$fieldName]);
          $activities[$activityId][$newFieldName] = $field;
        }
      }
      if (
        isset($activities[$activityId]['contract_updates_ch_recurring_contribution'])
        && $activities[$activityId]['contract_updates_ch_recurring_contribution']
      ) {
        /** @phpstan-var array<string, mixed> $rc */
        $rc = civicrm_api3(
          'ContributionRecur',
          'getsingle',
          ['id' => $activities[$activityId]['contract_updates_ch_recurring_contribution']]
        );
        $activities[$activityId]['payment_instrument_id'] = $rc['payment_instrument_id'];
        $activities[$activityId]['recurring_contribution_contact_id'] = $rc['contact_id'];
      }
      if (
        isset($activities[$activityId]['contract_updates_ch_annual'])
        && isset($activities[$activityId]['contract_updates_ch_frequency'])
        && $activities[$activityId]['contract_updates_ch_annual']
        && $activities[$activityId]['contract_updates_ch_frequency']
      ) {
        $activities[$activityId]['contract_updates_ch_amount'] = CRM_Contract_SepaLogic::formatMoney(
            $activities[$activityId]['contract_updates_ch_annual']
          ) / $activities[$activityId]['contract_updates_ch_frequency'];
        $activities[$activityId]['contract_updates_ch_amount'] = CRM_Contract_SepaLogic::formatMoney(
          $activities[$activityId]['contract_updates_ch_amount']
        );
      }
      if (isset($activities[$activityId]['campaign_id'])) {
        $campaigns[] = $activities[$activityId]['campaign_id'];
      }
      if (isset($activities[$activityId]['contract_cancellation_contact_history_cancel_reason'])) {
        $cancelReasons[] = $activities[$activityId]['contract_cancellation_contact_history_cancel_reason'];
      }
      if (isset($activities[$activityId]['source_contact_id'])) {
        $contacts[] = $activities[$activityId]['source_contact_id'];
      }

      // add title/hover title
      $display_titles = DisplayChangeTitle::renderDisplayChangeTitleAndHoverText(
                            $activities[$activityId]['activity_type_id'], $activities[$activityId]['id']);
      $activities[$activityId]['display_title'] = $display_titles->getDisplayTitle();
      $activities[$activityId]['display_hover_title'] = $display_titles->getDisplayHover();
    }

    $this->assign('activities', $activities);

    // Get campaigns
    if ([] !== $campaigns) {
      foreach (civicrm_api3('Campaign', 'get', ['id' => ['IN' => array_unique($campaigns)]])['values'] as $campaign) {
        $campaigns[$campaign['id']] = $campaign['title'];
      }
    }
    $this->assign('campaigns', $campaigns);
    if ([] !== $cancelReasons) {
      foreach (civicrm_api3(
        'OptionValue',
        'get',
        [
          'option_group_id' => 'contract_cancel_reason',
          'value' => ['IN' => array_unique($cancelReasons)],
        ]
      )['values'] as $campaign) {
        $cancelReasons[$campaign['value']] = $campaign['label'];
      }
    }
    $this->assign('cancelReasons', $cancelReasons);

    foreach (civicrm_api3('Contact', 'get', ['id' => ['IN' => array_unique($contacts)]])['values'] as $contact) {
      $contacts[$contact['id']] = $contact['display_name'];
    }
    $this->assign('contacts', $contacts);

    $mediums = [];
    foreach (civicrm_api3(
      'OptionValue',
      'get',
      ['option_group_id' => 'encounter_medium', 'return' => ['value', 'label']]
    )['values'] as $medium) {
      $mediums[$medium['value']] = $medium['label'];
    }
    $this->assign('mediums', $mediums);

    $paymentInstruments = [];
    foreach (civicrm_api3(
      'OptionValue',
      'get',
      [
        'option_group_id' => 'payment_instrument',
        'return' => ['value', 'label'],
      ]
    )['values'] as $paymentInstrument) {
      $paymentInstruments[$paymentInstrument['value']] = $paymentInstrument['label'];
    }
    $this->assign('paymentInstruments', $paymentInstruments);

    // Get activity statuses
    $activityStatuses = [];
    foreach (civicrm_api3(
      'OptionValue',
      'get',
      ['option_group_id' => 'activity_status', 'return' => ['value', 'label']]
    )['values'] as $activityStatus) {
      $activityStatuses[$activityStatus['value']] = $activityStatus['label'];
    }
    $this->assign('activityStatuses', $activityStatuses);

    $paymentFrequencies = [];
    foreach (civicrm_api3(
      'OptionValue',
      'get',
      ['option_group_id' => 'payment_frequency', 'return' => ['value', 'label']]
    )['values'] as $paymentFrequency) {
      $paymentFrequencies[$paymentFrequency['value']] = $paymentFrequency['label'];
    }
    $this->assign('paymentFrequencies', $paymentFrequencies);

    // Get activity types
    $this->assign('activityTypes', CRM_Contract_Change::getChangeTypes());
    $this->assign('includeWysiwygEditor', TRUE);

    // Get membership types
    $membershipTypes = [];
    foreach (civicrm_api3('MembershipType', 'get', [])['values'] as $membershipType) {
      $membershipTypes[$membershipType['id']] = $membershipType['name'];
    }
    $this->assign('membershipTypes', $membershipTypes);

    // since Civi 4.7, wysiwyg/ckeditor is a default core resource
    if (version_compare(CRM_Utils_System::version(), '4.7', '<')) {
      CRM_Core_Resources::singleton()->addScriptFile('civicrm', 'packages/ckeditor/ckeditor.js');
    }

    // hide some columns
    $this->assign('hide_columns', $adjustments->getHiddenColumnIndices());

    parent::run();
  }

}
