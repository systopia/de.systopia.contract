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

// phpcs:disable PSR1.Files.SideEffects
require_once 'contract.civix.php';
// phpcs:enable

use Civi\Contract\ContractManager;
use CRM_Contract_ExtensionUtil as E;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Implements hook_civicrm_container().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_container/
 */
function contract_civicrm_container(ContainerBuilder $container): void {
  if (class_exists('\Civi\Contract\ContainerSpecs')) {
    $container->addCompilerPass(new \Civi\Contract\ContainerSpecs());
  }

  $container->autowire(ContractManager::class);
  $container
    ->autowire(\Civi\Contract\Api4\Action\Contract\AddRelatedMemberAction::class)
    ->setPublic(TRUE);
  $container
    ->autowire(\Civi\Contract\Api4\Action\Contract\EndRelatedMemberAction::class)
    ->setPublic(TRUE);
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contract_civicrm_config(&$config): void {
  _contract_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contract_civicrm_install(): void {
  _contract_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contract_civicrm_enable(): void {
  _contract_civix_civicrm_enable();
}

/**
 * UI Adjustements for membership forms
 */
function contract_civicrm_pageRun(CRM_Core_Page &$page): void {
  /** @var string $page_name */
  $page_name = $page->getVar('_name');
  if ($page_name == 'CRM_Contribute_Page_ContributionRecur') {
    // this is a contribution view
    CRM_Contract_BAO_ContractPaymentLink::injectLinks($page);

  }
  elseif ($page_name == 'CRM_Contact_Page_View_Summary') {
    /** @var CRM_Contact_Page_View_Summary $page */
    // this is the contact summary page
    Civi::resources()
      ->addVars('contract', ['ce_activity_types' => CRM_Contract_Change::getActivityTypeIds()])
      ->addScriptUrl(E::url('js/hide-ce-activity-types.js'));

  }
  elseif ($page_name == 'CRM_Member_Page_Tab') {
    /** @var CRM_Member_Page_Tab $page */
    // thus is the membership summary tab
    $contractStatuses = [];
    foreach (
      civicrm_api3(
        'Membership',
        'get',
        ['contact_id' => $page->_contactId, 'options' => ['limit' => 0]]
      )['values'] as $contract
    ) {
      $contractStatuses[$contract['id']] = civicrm_api3(
        'Contract',
        'get_open_modification_counts',
        ['id' => $contract['id'], 'options' => ['limit' => 0]]
      )['values'];
    }
    CRM_Core_Resources::singleton()->addStyleFile(E::LONG_NAME, 'css/contract.css');
    CRM_Core_Resources::singleton()->addVars('contract', ['contractStatuses' => $contractStatuses]);
    CRM_Core_Resources::singleton()->addVars('contract', ['cid' => $page->_contactId]);
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'templates/CRM/Member/Page/Tab.js');
    CRM_Core_Resources::singleton()->addVars('contract', [
      'reviewLinkTitles' => [
        'needs review' => E::ts('needs review'),
        'scheduled modifications' => E::ts('scheduled modifications'),
        'scheduled review' => E::ts('scheduled review'),
        'hide' => E::ts('hide'),
      ],
    ]);
  }
}

/**
 * UI Adjustments for membership forms
 *
 * @todo shorten this function call - move into an 1 or more alter functions
 */
// phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
function contract_civicrm_buildForm(string $formName, CRM_Core_Form &$form): void {
// phpcs:enable
  switch ($formName) {
    // Membership form in view mode
    case 'CRM_Member_Form_MembershipView':
      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);

      $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
      $formUtils->setPaymentAmountCurrency();
      $formUtils->replaceIdWithLabel('membership_payment.membership_recurring_contribution', 'ContributionRecur');
      $formUtils->replaceIdWithLabel('membership_payment.payment_instrument', 'PaymentInstrument');
      $formUtils->replaceIdWithLabel('membership_payment.to_ba', 'BankAccountReference');
      $formUtils->replaceIdWithLabel('membership_payment.from_ba', 'BankAccountReference');

      // Add link for contract download
      $membershipId = CRM_Utils_Request::retrieve('id', 'Positive', $form);
      $formUtils->addMembershipContractFileDownloadLink($membershipId);

      // GP-814 - hide 'edit' button if 'edit core membership CiviContract' is not granted
      if (!CRM_Core_Permission::check('edit core membership CiviContract')) {
        CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/membership_view_hide_edit.js');
      }

      break;

    // Membership form in add mode
    case 'CRM_Member_Form_Membership':

      $contactId = CRM_Utils_Request::retrieve('cid', 'Positive', $form);
      $id = CRM_Utils_Request::retrieve('id', 'Positive', $form);

      if (in_array($form->getAction(), [CRM_Core_Action::UPDATE, CRM_Core_Action::ADD], TRUE)) {
        // Use JS to hide form elements
        CRM_Core_Resources::singleton()
          ->addScriptFile(E::LONG_NAME, 'templates/CRM/Member/Form/Membership.js');
        $filteredMembershipStatuses = civicrm_api3('MembershipStatus', 'get', [
          'name' => [
            'IN' => [
              'Current',
              'Cancelled',
            ],
          ],
        ]);
        CRM_Core_Resources::singleton()
          ->addVars('contract', ['filteredMembershipStatuses' => $filteredMembershipStatuses]);
        $hiddenCustomFields = civicrm_api3('CustomField', 'get', [
          'name' => [
            'IN' => [
              'membership_annual',
              'membership_frequency',
            ],
          ],
        ]);
        CRM_Core_Resources::singleton()
          ->addVars('contract', ['hiddenCustomFields' => $hiddenCustomFields]);

        if ($form->getAction() == CRM_Core_Action::ADD) {
          $form->setDefaults([
            'is_override' => TRUE,
            'status_id' => civicrm_api3('MembershipStatus', 'getsingle', ['name' => 'current'])['id'],
          ]);
        }

        $formUtils = new CRM_Contract_FormUtils($form, 'Membership');
        if (isset($form->_groupTree)) {
          // NOTE for initial launch: all core membership fields should be editable
          // $formUtils->removeMembershipEditDisallowedCoreFields();
          // NOTE for initial launch: allow editing of payment contracts via the standard form

          // Custom data version

          /** @phpstan-var array{id: int, custom_group_id: int} $result */
          $result = civicrm_api3('CustomField', 'GetSingle', [
            'custom_group_id' => 'membership_payment',
            'name' => 'membership_recurring_contribution',
          ]);
          $customGroupTableId = isset($form->_groupTree[$result['custom_group_id']]['table_id'])
            ? $form->_groupTree[$result['custom_group_id']]['table_id']
            : '-1';
          $elementName = "custom_{$result['id']}_{$customGroupTableId}";
          $form->removeElement($elementName);
          $formUtils->addPaymentContractSelect2($elementName, $contactId, TRUE, $id);
          // NOTE for initial launch: all custom membership fields should be editable
          $formUtils->removeMembershipEditDisallowedCustomFields();
        }
      }

      if ($form->getAction() === CRM_Core_Action::ADD) {
        if ($cid = CRM_Utils_Request::retrieve('cid', 'Integer')) {
          /** @var int $cid */
          // if the cid is given, it's the "add membership" for an existing contract
          $contract_create_form_url = \Civi\Contract\Event\ContractCreateFormEvent::getUrl($cid);
          if (is_string($contract_create_form_url)) {
            CRM_Utils_System::redirect($contract_create_form_url);
          }
        }
        else {
          // no id - this is a 'create new membership':
          //   check if somebody registered a rapid create form and redirect
          $rapid_create_form_url = \Civi\Contract\Event\RapidCreateFormEvent::getUrl();
          if (is_string($rapid_create_form_url)) {
            CRM_Utils_System::redirect($rapid_create_form_url);
          }
        }
      }

      // workaround for GP-671
      if ($form->getAction() === CRM_Core_Action::UPDATE) {
        CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/membership_edit_protection.js');
      }
      break;

    //Activity form in view mode
    case 'CRM_Activity_Form_Activity':
    case 'CRM_Fastactivity_Form_Add':
    case 'CRM_Fastactivity_Form_View':
      if ($form->getAction() == CRM_Core_Action::VIEW) {

        // Show recurring contribution details
        $id = CRM_Utils_Request::retrieve('id', 'Positive', $form);
        $formUtils = new CRM_Contract_FormUtils($form, 'Activity');
        $formUtils->replaceIdWithLabel('contract_updates.ch_recurring_contribution', 'ContributionRecur');
        $formUtils->replaceIdWithLabel('contract_updates.ch_payment_instrument', 'PaymentInstrument');
        $formUtils->replaceIdWithLabel('contract_updates.ch_from_ba', 'BankAccountReference');
        $formUtils->replaceIdWithLabel('contract_updates.ch_to_ba', 'BankAccountReference');

        // Show membership label, not id
        $formUtils->showMembershipTypeLabel();

      }
      elseif ($form->getAction() == CRM_Core_Action::UPDATE) {
        CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'templates/CRM/Activity/Form/Edit.js');
      }
      break;

  }
}

/**
 * Custom validation for membership forms
 */
function contract_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if (
    $formName == 'CRM_Member_Form_Membership'
    && in_array($form->getAction(), [CRM_Core_Action::UPDATE, CRM_Core_Action::ADD])
  ) {
    CRM_Contract_Handler_MembershipForm::validateForm($formName, $fields, $files, $form, $errors);
  }
}

/**
 * Custom links for memberships
 */
function contract_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if ($objectName == 'Membership') {
    if ($objectId) {
      // load membership
      $membership_data = civicrm_api3('Membership', 'getsingle', ['id' => $objectId]);

      // alter links
      CRM_Contract_Change::modifyActionLinks($membership_data, $links);
    }

  }
  elseif ($op == 'contribution.selector.row') {
    // add a Contract link to contributions that are connected to memberships
    $contribution_id = (int) $objectId;
    if ($contribution_id) {
      // add 'view contract' link
      $membership_id = CRM_Core_DAO::singleValueQuery(
        "SELECT membership_id FROM civicrm_membership_payment WHERE contribution_id = {$contribution_id} LIMIT 1"
      );
      if ($membership_id) {
        $contact_id = CRM_Core_DAO::singleValueQuery(
          "SELECT contact_id FROM civicrm_membership WHERE id = {$membership_id} LIMIT 1"
        );
        if ($contact_id) {
          $links[] = [
            'name'  => 'Contract',
            'title' => 'View Contract',
            'url'   => 'civicrm/contact/view/membership',
            'qs'    => "reset=1&id={$membership_id}&cid={$contact_id}&action=view",
          ];
        }
      }
    }
  }
}

/**
 * CiviCRM PRE hook: Monitoring of relevant entity changes
 */
function _contract_civicrm_pre($op, $objectName, $id, &$params) {
  // FIXME: Monitoring currently not implemented in the new engine
}

/**
 * CiviCRM POST hook: Monitoring of relevant entity changes
 */
function _contract_civicrm_post($op, $objectName, $id, &$objectRef) {
  // FIXME: Monitoring currently not implemented in the new engine
}

/**
 * Add config link
 */
function contract_civicrm_navigationMenu(&$menus) {
  // Find the mailing menu
  foreach ($menus as &$menu) {
    if ($menu['attributes']['name'] == 'Memberships') {
      $nextId = max(array_keys($menu['child']));
      $menu['child'][$nextId] = [
        'attributes' => [
          'label'      => 'Contract settings',
          'name'       => 'Contract settings',
          'url'        => 'civicrm/admin/contract',
          'permission' => 'access CiviMember',
          'navID'      => $nextId,
          'operator'   => FALSE,
          'separator'  => TRUE,
          'parentID'   => $menu['attributes']['navID'],
          'active'     => 1,
        ],
      ];
    }
  }
}

/**
 * Implements hook_civicrm_apiWrappers().
 */
function contract_civicrm_apiWrappers(&$wrappers, $apiRequest) {
  // add contract reference validation for Memberships
  if ($apiRequest['entity'] == 'Membership') {
    $wrappers[] = new CRM_Contract_Handler_MembershipAPI();
  }
}

/**
 * Add an "Assign to Campaign" for contact / membership search results
 *
 * @param string $objectType specifies the component
 * @param array $tasks the list of actions
 *
 * @access public
 */
function contract_civicrm_searchTasks($objectType, &$tasks) {
  if ($objectType == 'contribution') {
    if (CRM_Core_Permission::check('edit memberships')) {
      $tasks[] = [
        'title' => E::ts('Assign to Contract'),
        'class' => 'CRM_Contract_Form_Task_AssignContributions',
        'result' => FALSE,
      ];
      $tasks[] = [
        'title' => E::ts('Detach from Contract'),
        'class' => 'CRM_Contract_Form_Task_DetachContributions',
        'result' => FALSE,
      ];
    }
  }
}

/**
 * Add CiviContract permissions
 *
 * @param $permissions
 */
function contract_civicrm_permission(&$permissions) {
  $permissions['edit core membership CiviContract'] = [
    'label'       => E::ts('CiviContract: Edit core membership'),
    'description' => E::ts('Allow editing memberships using the core membership form'),
  ];
}

/**
 * Entity Types Hook
 * @param $entityTypes
 */
