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
 * "Cancel Membership" change
 */
class CRM_Contract_Change_Cancel extends CRM_Contract_Change {

  private const MEMBERSHIP_CANCEL_REASON = 'membership_cancellation.membership_cancel_reason';
  private const MEMBERSHIP_CANCEL_DATE   = 'membership_cancellation.membership_cancel_date';

  /**
   * Get a list of required fields for this type
   *
   * @return array list of required fields
   */
  public function getRequiredFields() {
    return [
      self::MEMBERSHIP_CANCEL_REASON,
    ];
  }

  /**
   * Derive/populate additional data
   */
  public function populateData() {
    if ($this->isNew()) {
      $this->setParameter(
        'contract_cancellation.contact_history_cancel_reason',
        $this->getParameter(self::MEMBERSHIP_CANCEL_REASON)
      );
      $this->setParameter('subject', $this->getSubject(NULL));
    }
    else {
      parent::populateData();
      $this->setParameter(
        self::MEMBERSHIP_CANCEL_REASON,
        $this->getParameter('contract_cancellation.contact_history_cancel_reason')
      );
    }
  }

  /**
   * Apply the given change to the contract
   *
   * @throws Exception should anything go wrong in the execution
   */
  public function execute() {
    $contract = $this->getContract();

    // cancel the contract by setting the end date
    $contract_update = [
      'end_date'                     => date('YmdHis'),
      self::MEMBERSHIP_CANCEL_REASON => $this->data[self::MEMBERSHIP_CANCEL_REASON],
      self::MEMBERSHIP_CANCEL_DATE   => date('YmdHis'),
      'status_id'                    => 'Cancelled',
    ];

    // perform the update
    $this->updateContract($contract_update);

    // also: cancel the mandate/recurring contribution
    CRM_Contract_SepaLogic::terminateSepaMandate(
        $contract['membership_payment.membership_recurring_contribution'],
        $this->data[self::MEMBERSHIP_CANCEL_REASON]);

    // update change activity
    $contract_after = $this->getContract();
    $this->setParameter('subject', $this->getSubject($contract_after, $contract));
    $this->setStatus('Completed');
    $this->save();
  }

  /**
   * Check whether this change activity should actually be created
   *
   * CANCEL activities should not be created, if there is another one already there
   *
   * @throws Exception if the creation should be disallowed
   */
  public function shouldBeAccepted() {
    parent::shouldBeAccepted();

    // check for OTHER CANCELLATION REQUEST for the same day
    //  @see https://redmine.greenpeace.at/issues/1190
    $requested_day = date('Y-m-d', strtotime($this->data['activity_date_time']));
    $scheduled_activities = civicrm_api3('Activity', 'get', [
      'source_record_id' => $this->getContractID(),
      'activity_type_id' => $this->getActvityTypeID(),
      'status_id'        => 'Scheduled',
      'option.limit'     => 0,
      'sequential'       => 1,
      'return'           => 'id,activity_date_time',
    ]);
    foreach ($scheduled_activities['values'] as $scheduled_activity) {
      $scheduled_for_day = date('Y-m-d', strtotime($scheduled_activity['activity_date_time']));
      if ($scheduled_for_day == $requested_day) {
        // there's already a scheduled 'cancel' activity for the same day
        throw new Exception('Scheduling an (additional) cancellation request in not desired in this context.');
      }
    }

    // IF CONTRACT ALREADY CANCELLED, create another cancel activity only
    //  when there are other scheduled (or 'needs review') changes
    //  @see https://redmine.greenpeace.at/issues/1190
    $contract = $this->getContract();

    $contract_cancelled_status = civicrm_api3('MembershipStatus', 'get', [
      'name'   => 'Cancelled',
      'return' => 'id',
    ]);
    if ($contract['status_id'] == $contract_cancelled_status['id']) {
      // contract is cancelled
      $pending_activity_count = civicrm_api3('Activity', 'getcount', [
        'source_record_id' => $this->getContractID(),
        'activity_type_id' => ['IN' => CRM_Contract_Change::getActivityTypeIds()],
        'status_id'        => ['IN' => ['Scheduled', 'Needs Review']],
      ]);
      if ($pending_activity_count == 0) {
        throw new Exception('Scheduling an (additional) cancellation request in not desired in this context.');
      }
    }
  }

  /**
   * Render the default subject
   *
   * @param $contract_after       array  data of the contract after
   * @param $contract_before      array  data of the contract before
   * @return                      string the subject line
   */
  public function renderDefaultSubject($contract_after, $contract_before = NULL) {
    if ($this->isNew()) {
      return 'Cancel Contract';
    }
    else {
      $contract_id = $this->getContractID();
      $subject = "id{$contract_id}:";
      if (!empty($this->data['contract_cancellation.contact_history_cancel_reason'])) {
        // TODO: not needed any more? (see https://redmine.greenpeace.at/issues/1276#note-74)
        // FIXME: replicating weird behaviour by old engine
        $subject .= ' cancel reason ' . $this->labelValue(
            $this->data['contract_cancellation.contact_history_cancel_reason'],
            'contract_cancellation.contact_history_cancel_reason'
          );
      }
      return $subject;
    }
  }

  /**
   * Get a list of the status names that this change can be applied to
   *
   * @return array list of membership status names
   */
  public static function getStartStatusList() {
    return ['New', 'Grace', 'Current', 'Pending'];
  }

  /**
   * Get a (human readable) title of this change
   *
   * @return string title
   */
  public static function getChangeTitle() {
    return E::ts('Cancel Contract');
  }

  /**
   * Modify action links provided to the user for a given membership
   *
   * @param $links                array  currently given links
   * @param $current_status_name  string membership status as a string
   * @param $membership_data      array  all known information on the membership in question
   */
  public static function modifyMembershipActionLinks(&$links, $current_status_name, $membership_data) {
    if (in_array($current_status_name, self::getStartStatusList())) {
      $links[] = [
        'name'  => E::ts('Cancel'),
        'title' => self::getChangeTitle(),
        'url'   => 'civicrm/contract/modify',
        'bit'   => CRM_Core_Action::UPDATE,
        'qs'    => 'modify_action=cancel&id=%%id%%',
      ];
    }
  }

}
