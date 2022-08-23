<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Create extends CRM_Core_Form {

  function buildQuickForm() {
    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (empty($this->cid)) {
      $this->cid = $this->get('cid');
    }
    if($this->cid){
      $this->set('cid', $this->cid);
    } else {
      CRM_Core_Error::statusBounce('You have to specify a contact ID to create a new contract');
    }
    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->get('cid')}&selectedChild=member");

    $this->assign('cid', $this->get('cid'));
    $this->contact = civicrm_api3('Contact', 'getsingle', ['id' => $this->get('cid')]);
    $this->assign('contact', $this->contact);
    self::setTitle(E::ts("Create a new contract for %1", [1 => $this->contact['display_name']]));

    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2('recurring_contribution', $this->get('cid'), false, null);
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', array(
      'cid'                     => $this->get('cid'),
      'debitor_name'            => $this->contact['display_name'],
      'creditor'                => CRM_Contract_SepaLogic::getCreditor(),
      // 'next_collections'        => CRM_Contract_SepaLogic::getNextCollections(),
      'frequencies'             => CRM_Contract_SepaLogic::getPaymentFrequencies(),
      'grace_end'               => NULL,
      'recurring_contributions' => CRM_Contract_RecurringContribution::getAllForContact($this->get('cid'))));
    CRM_Contract_SepaLogic::addJsSepaTools();

    // Payment dates
    $this->add('select', 'payment_option', E::ts('Payment'), array('create' => E::ts('create new mandate'), 'select' => E::ts('select existing contract')));
    $this->add('select', 'cycle_day', E::ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays());
    $this->add('text',   'iban', E::ts('IBAN'), array('class' => 'huge'));
    $this->add('text',   'bic', E::ts('BIC'));
    $this->add('text',   'account_holder', E::ts('Members Bank Account'), array('class' => 'huge'));
    $this->add('text',   'payment_amount', E::ts('Installment amount'), array('size' => 6));
    $this->add('select', 'payment_frequency', E::ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies());
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // Contract dates
    $this->addDate('join_date', E::ts('Member since'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('start_date', E::ts('Membership start date'), TRUE, array('formatType' => 'activityDate'));
    $this->addDate('end_date', E::ts('End date'), FALSE, array('formatType' => 'activityDate'));

    // campaign selector
    $this->add('select', 'campaign_id', E::ts('Campaign'), CRM_Contract_Configuration::getCampaignList(), FALSE, array('class' => 'crm-select2'));
    // $this->addEntityRef('campaign_id', E::ts('Campaign'), [
    //   'entity' => 'campaign',
    //   'placeholder' => E::ts('- none -')
    // ]);

    // Membership type (membership)
    foreach(civicrm_api3('MembershipType', 'get', ['options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    }
    $this->add('select', 'membership_type_id', E::ts('Membership type'), array('' => '- none -') + $MembershipTypeOptions, true, array('class' => 'crm-select2'));

    // Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id", 'options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', E::ts('Source media'), array('' => '- none -') + $mediumOptions, false, array('class' => 'crm-select2'));

    // Reference number text
    $this->add('text', 'membership_reference', E::ts('Reference number'));

    // Contract number text
    $this->add('text', 'membership_contract', E::ts('Contract number'));

    // DD-Fundraiser
    $this->addEntityRef('membership_dialoger', E::ts('DD-Fundraiser'), array('api' => array('params' => array('contact_type' => 'Individual', 'contact_sub_type' => 'Dialoger'))));

    // Membership channel
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contact_channel',
      'is_active'       => 1,
      'options'         => ['limit' => 0, 'sort' => 'weight']])['values'] as $optionValue){
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    }
    $this->add('select', 'membership_channel', E::ts('Membership channel'), array('' => '- none -') + $membershipChannelOptions, false, array('class' => 'crm-select2'));

    // Notes
    if (version_compare(CRM_Utils_System::version(), '4.7', '<')) {
      $this->addWysiwyg('activity_details', E::ts('Notes'), []);
    } else {
      $this->add('wysiwyg', 'activity_details', E::ts('Notes'));
    }

    // add the JS file for the payment preview
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/contract_modify_tools.js');

    $this->addButtons([
      ['type' => 'cancel', 'name' => E::ts('Cancel'), 'submitOnce' => TRUE],
      ['type' => 'submit', 'name' => E::ts('Create'), 'submitOnce' => TRUE],
    ]);

    $this->setDefaults();

  }

  /**
   * form validation
   */
  function validate() {
    $submitted = $this->exportValues();

    // check if all values for 'create new mandate' are there
    if ($submitted['payment_option'] == 'create') {
      if(empty($submitted['payment_frequency'])) {
        HTML_QuickForm::setElementError ( 'payment_frequency', 'Please specify a frequency');
      }
      if(empty($submitted['payment_amount'])) {
        HTML_QuickForm::setElementError ( 'payment_amount', 'Please specify an amount');
      }

      // $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'] / $submitted['payment_frequency']);
      // if ($amount < 0.01) {
      //   HTML_QuickForm::setElementError ( 'payment_amount', 'Annual amount too small.');
      // }

      // SEPA validation
      if (empty($submitted['iban'])) {
        HTML_QuickForm::setElementError ( 'iban', 'IBAN required');
      }

      if (empty($submitted['bic'])) {
        HTML_QuickForm::setElementError ( 'bic', 'BIC required');
      }

      if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
        HTML_QuickForm::setElementError ( 'iban', 'Please enter a valid IBAN');
      }
      if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
        HTML_QuickForm::setElementError ( 'iban', "Do not use any of the organisation's own IBANs");
      }
      if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
        HTML_QuickForm::setElementError ( 'bic', 'Please enter a valid BIC');
      }

      if (!empty($submitted['join_date']) && CRM_Utils_Date::processDate(date('Ymd')) < CRM_Utils_Date::processDate($submitted['join_date'])) {
        HTML_QuickForm::setElementError('join_date', ts('Join date cannot be in the future.'));
      }
    }

    return parent::validate();
  }


  function setDefaults($defaultValues = null, $filter = null) {

    list($defaults['join_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');
    list($defaults['start_date'], $null) = CRM_Utils_Date::setDateDefaults(NULL, 'activityDateTime');

    // sepa defaults
    $defaults['payment_frequency'] = '12'; // monthly
    $defaults['payment_option'] = 'create';
    $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();

    parent::setDefaults($defaults);
  }

  function postProcess() {
    $submitted = $this->exportValues();

    if ($submitted['payment_option'] == 'create') {
        // calculate some stuff
        if ($submitted['cycle_day'] < 1 || $submitted['cycle_day'] > 30) {
          // invalid cycle day
          $submitted['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
        }

        // calculate amount
        //TODO we can probably remove the calculation of $annual_amount
        $annual_amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_frequency'] * CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']));
        $frequency_interval = 12 / $submitted['payment_frequency'];
        $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']);

        $new_mandate = CRM_Contract_SepaLogic::createNewMandate(array(
              'type'               => 'RCUR',
              'contact_id'         => $this->get('cid'),
              'amount'             => $amount,
              'currency'           => CRM_Contract_SepaLogic::getCreditor()->currency,
              'start_date'         => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
              'creation_date'      => date('YmdHis'), // NOW
              'date'               => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
              'validation_date'    => date('YmdHis'), // NOW
              'iban'               => $submitted['iban'],
              'bic'                => $submitted['bic'],
              'account_holder'     => $submitted['account_holder'],
              // 'source'             => ??
              'campaign_id'        => $submitted['campaign_id'],
              'financial_type_id'  => 2, // Membership Dues
              'frequency_unit'     => 'month',
              'cycle_day'          => $submitted['cycle_day'],
              'frequency_interval' => $frequency_interval,
            ));
        $params['membership_payment.membership_recurring_contribution'] = $new_mandate['entity_id'];
        $params['membership_general.membership_dialoger'] = $submitted['membership_dialoger']; // DD fundraiser
    } else {
        $params['membership_payment.membership_recurring_contribution'] = $submitted['recurring_contribution']; // Recurring contribution
    }


    // Create the contract (the membership)

    // Core fields
    $params['contact_id'] = $this->get('cid');
    $params['membership_type_id'] = $submitted['membership_type_id'];
    $params['start_date'] = CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s');
    $params['join_date'] = CRM_Utils_Date::processDate($submitted['join_date'], null, null, 'Y-m-d H:i:s');

    // TODO Marco: should we remove start date from this form? As it should only be set when a contract is cancelled
    if($submitted['end_date']){
      $params['end_date'] = CRM_Utils_Date::processDate($submitted['end_date'], null, null, 'Y-m-d H:i:s');
    }
    $params['campaign_id'] = $submitted['campaign_id'];

    // 'Custom' fields
    $params['membership_general.membership_reference'] = $submitted['membership_reference']; // Reference number
    $params['membership_general.membership_contract']  = $submitted['membership_contract'];  // Contract number
    $params['membership_general.membership_dialoger']  = $submitted['membership_dialoger'];  // DD fundraiser
    $params['membership_general.membership_channel']   = $submitted['membership_channel'];   // Membership Channel

    $params['membership_payment.from_name'] = $submitted['account_holder'];
    $params['note'] = $submitted['activity_details']; // Membership channel
    $params['medium_id'] = $submitted['activity_medium']; // Membership channel

    $membershipResult = civicrm_api3('Contract', 'create', $params);

    // update and redirect
    $this->ajaxResponse['updateTabs']['#tab_sepa'] = 1;
    $this->ajaxResponse['updateTabs']['#tab_member'] = 1;
    $this->controller->_destination = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->get('cid')}");
  }
}
