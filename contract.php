<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

require_once 'contract.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contract_civicrm_config(&$config) {
  _contract_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function contract_civicrm_xmlMenu(&$files) {
  _contract_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contract_civicrm_install() {
  _contract_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function contract_civicrm_postInstall() {
  _contract_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contract_civicrm_uninstall() {
  _contract_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contract_civicrm_enable() {
  require_once 'CRM/Contract/CustomData.php';
  $customData = new CRM_Contract_CustomData('de.systopia.contract');
  $customData->syncOptionGroup(__DIR__ . '/resources/contract_cancel_reason_option_group.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/payment_frequency_option_group.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/activity_types_option_group.json');
  $customData->syncOptionGroup(__DIR__ . '/resources/activity_status_option_group.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/contract_cancellation_custom_group.json');
  $customData->syncCustomGroup(__DIR__ . '/resources/contract_updates_custom_group.json');
  // TODO We need to account for membership custom data
  // $customData->syncCustomGroup(__DIR__ . '/resources/membership_payment_custom_group.json');
  $customData->syncEntities(__DIR__ . '/resources/membership_status_entities.json');

  _contract_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contract_civicrm_disable() {
  _contract_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contract_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contract_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
// function contract_civicrm_managed(&$entities) {
//   _contract_civix_civicrm_managed($entities);
// }

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function contract_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _contract_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

function contract_civicrm_pageRun( &$page ){
  if($page->getVar('_name') == 'CRM_Member_Page_Tab'){
    foreach(civicrm_api3('Membership', 'get', ['contact_id' => $page->_contactId])['values'] as $contract){
      $contractStatuses[$contract['id']] = civicrm_api3('Contract', 'get_open_modification_counts', ['id' => $contract['id']]);
    }
    CRM_Core_Resources::singleton()->addStyleFile('de.systopia.contract', 'css/contract.css');
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('contractStatuses' => $contractStatuses));
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('cid' => $page->_contactId));
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'templates/CRM/Member/Page/Tab.js');
  }
}

//TODO - shorten this function call - move into an 1 or more alter functions
function contract_civicrm_buildForm($formName, &$form) {

  switch ($formName) {

    // Membership form in view mode
    case 'CRM_Member_Form_MembershipView':
      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
      $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
      $formUtils->showPaymentContractDetails();
      break;

    // Membership form in add mode
    case 'CRM_Member_Form_Membership':

      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);

      if(in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){

        // Use JS to hide form elements
        CRM_Core_Resources::singleton()->addScriptFile( 'de.systopia.contract', 'templates/CRM/Member/Form/Membership.js' );
        $filteredMembershipStatuses = civicrm_api3('MembershipStatus', 'get', ['name' => ['IN' => ['Current', 'Cancelled']]]);
        CRM_Core_Resources::singleton()->addVars( 'de.systopia.contract', ['filteredMembershipStatuses' => $filteredMembershipStatuses]);
        $hiddenCustomFields = civicrm_api3('CustomField', 'get', ['name' => ['IN' => ['membership_annual', 'membership_frequency']]]);
        CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array('hiddenCustomFields' => $hiddenCustomFields));

        if($form->getAction() == CRM_Core_Action::ADD){
          $form->setDefaults(array(
            'is_override' => true,
            'status_id' => civicrm_api3('MembershipStatus', 'getsingle', array('name' => "current"))['id']
          ));
        }

        $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
        if(!isset($form->_groupTree)){
          // NOTE for initial launch: all core membership fields should be editable
          // $formUtils->removeMembershipEditDisallowedCoreFields();
          // NOTE for initial launch: allow editing of payment contracts via the standard form

        // Custom data version
        }else{
          $result = civicrm_api3('CustomField', 'GetSingle', array('custom_group_id' => 'membership_payment', 'name' => 'membership_recurring_contribution'));
          $customGroupTableId = isset($form->_groupTree[$result['custom_group_id']]['table_id']) ? $form->_groupTree[$result['custom_group_id']]['table_id'] : '-1';
          $elementName = "custom_{$result['id']}_{$customGroupTableId}";
          $form->removeElement($elementName);
          $formUtils->addPaymentContractSelect2($elementName, $contactId);
          // NOTE for initial launch: all custom membership fields should be editable
          $formUtils->removeMembershipEditDisallowedCustomFields();
        }
      }
      if($form->getAction() === CRM_Core_Action::ADD){
        if($cid = CRM_Utils_Request::retrieve('cid', 'Integer')){
          CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/create', 'cid='.$cid, true));
        }else{
          CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/contract/rapidcreate', true));

        }
      }
      break;

    //Activity form in view mode
    case 'CRM_Activity_Form_Activity':
      if($form->getAction() == CRM_Core_Action::VIEW){

        // Show recurring contribution details
        $id =  CRM_Utils_Request::retrieve('id', 'Positive', $form);
        $formUtils = new CRM_Contract_FormUtils($form, 'Activity');
        $formUtils->showPaymentContractDetails();

        // Show membership label, not id
        $formUtils->showMembershipTypeLabel();

      }
      break;

  }
}

function contract_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors){
  if($formName == 'CRM_Member_Form_Membership' && in_array($form->getAction(), array(CRM_Core_Action::UPDATE, CRM_Core_Action::ADD))){
    $wrapper = new CRM_Contract_Wrapper_MembershipEditForm();
    $wrapper->validate($form, CRM_Utils_Request::retrieve('id', 'Positive', $form), $fields);
    $errors = $wrapper->getErrors();
  }
}

function contract_civicrm_links( $op, $objectName, $objectId, &$links, &$mask, &$values ){
  switch ($objectName) {

    // Change membership edit links
    case 'Membership':
      $alter = new CRM_Contract_AlterMembershipLinks($objectId, $links, $mask, $values);
      $alter->removeActions(array(CRM_Core_Action::RENEW, CRM_Core_Action::DELETE, CRM_Core_Action::UPDATE));
      $alter->addHistoryActions();
      break;
    }
}

function contract_civicrm_pre($op, $objectName, $id, &$params){

  // Using elseif would save *some* resources but make the code more brittle
  // since the comparisons are a little involved and may change over time.
  // Lets keep them independent by just using ifs

  // Wrap calls to the Membership BAO so we can reverse engineer modification
  // activities if necessary
  if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    $wrapperMembership = CRM_Contract_Wrapper_Membership::singleton();
    $wrapperMembership->pre($op, $id, $params);
  }

  // Wrap calles to the Activity BAO so we can execute contract modifications
  // and check for potential conflicts as appropriate

  if($objectName == 'Activity'){

    // TODO address weird CiviCRM where this hook is fired when deleting a
    // membership via the UI. It gets called with $op == 'delete', a null $id
    // and some odd params. This solves the issue for now.
    if($op == 'delete' && is_null($id)){
      return;
    }
    $wrapperModificationActivity = CRM_Contract_Wrapper_ModificationActivity::singleton();
    $wrapperModificationActivity->pre($op, $params);
  }
}

function contract_civicrm_post($op, $objectName, $id, &$objectRef){

  // Wrap calls to the Membership BAO so we can reverse engineer modification
  // activities if necessary
  if($objectName == 'Membership' && in_array($op, array('create', 'edit'))){
    $wrapperMembership = CRM_Contract_Wrapper_Membership::singleton();
    $wrapperMembership->post($id);
  }

  // Wrap calles to the Activity BAO to execute contract
  // modifications and check when appropriate
  if($objectName == 'Activity'){
    $wrapperModificationActivity = CRM_Contract_Wrapper_ModificationActivity::singleton();
    $wrapperModificationActivity->post($id, $objectRef);
  }

  // Delete build in membership log activities
  if($objectName == 'Activity'){
    if($op == 'create' && in_array($objectRef->activity_type_id, CRM_Contract_Utils::getCoreMembershipHistoryActivityIds())){
      civicrm_api3('Activity', 'delete', array('id' => $id));
    }
  }
}
