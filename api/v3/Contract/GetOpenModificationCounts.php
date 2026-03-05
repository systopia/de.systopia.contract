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

/**
 * Get the number of scheduled modifications for a contract
 */
function _civicrm_api3_Contract_get_open_modification_counts_spec(&$params) {
  $params['id'] = [
    'name'         => 'id',
    'title'        => 'Contract ID',
    'api.required' => 1,
    'description'  => 'Contract (Membership) ID of the contract to be modified',
  ];
}

/**
 * Get the number of scheduled modifications for a contract
 */
function civicrm_api3_Contract_get_open_modification_counts($params) {
  $activitiesForReview = Activity::get(FALSE)
    ->selectRowCount()
    ->addWhere('contract_activity.contract_id', '=', $params['id'])
    ->addWhere('status_id:name', '=', 'Needs Review')
    ->execute()
    ->count();
  $activitiesScheduled = Activity::get(FALSE)
    ->selectRowCount()
    ->addWhere('contract_activity.contract_id', '=', $params['id'])
    ->addWhere('status_id:name', '=', 'Scheduled')
    ->execute()
    ->count();
  $activitiesFailed = Activity::get(FALSE)
    ->selectRowCount()
    ->addWhere('contract_activity.contract_id', '=', $params['id'])
    ->addWhere('status_id:name', '=', 'Failed')
    ->execute()
    ->count();
  return civicrm_api3_create_success([
    'needs_review' => $activitiesForReview,
    'scheduled' => $activitiesScheduled,
    'failed' => $activitiesFailed,
  ]);
}
