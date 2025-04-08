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
use \Civi\Contract\Event\ContractFormDefaultsEvent as ContractFormDefaultsEvent;

class CRM_Contract_Form_Create extends CRM_Core_Form {

  /** @var ?int contact ID */
  protected ?int $cid;

  /** @var array contact  data on the membership's contact */
  protected array $contact;

  function buildQuickForm() {
    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (empty($this->cid)) {
      $this->cid = $this->get('cid');
    }
    if ($this->cid){
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
    CRM_Core_Resources::singleton()->addVars('de.systopia.contract', [
      'cid'                     => $this->get('cid'),
      'debitor_name'            => $this->contact['display_name'],
      'creditor'                => CRM_Contract_SepaLogic::getCreditor(),
      // 'next_collections'        => CRM_Contract_SepaLogic::getNextCollections(),
      'frequencies'             => CRM_Contract_SepaLogic::getPaymentFrequencies(),
      'grace_end'               => NULL,
      'recurring_contributions' => CRM_Contract_RecurringContribution::getAllForContact($this->get('cid'))]);

    CRM_Contract_SepaLogic::addJsSepaTools();

    // Payment dates
    //$this->add('select', 'payment_option', E::ts('Payment'), ['create' => E::ts('create new mandate'), 'select' => E::ts('select existing contract')]);
    $this->add('select', 'payment_option', E::ts('Payment'), $this->getPaymentOptions(), true, ['class' => 'crm-select2']);
    $this->add('select', 'cycle_day', E::ts('Cycle day'), CRM_Contract_SepaLogic::getCycleDays(), true, ['class' => 'crm-select2']);
    $this->add('text',   'iban', E::ts('IBAN'), ['class' => 'huge']);
    $this->add('text',   'bic', E::ts('BIC'), ['class' => 'normal', 'placeholder' => 'NOTPROVIDED'], false);
    $this->add('text',   'account_holder', E::ts('Members Bank Account'), ['class' => 'huge', 'placeholder' => $this->contact['display_name']]);
    $this->add('text',   'payment_amount', E::ts('Installment amount'), ['size' => 6, 'placeholder' => E::ts("Installment")]);
    $this->add('select', 'payment_frequency', E::ts('Payment Frequency'), CRM_Contract_SepaLogic::getPaymentFrequencies(), true, ['class' => 'crm-select2']);
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // Contract dates
    $this->add('datepicker', 'join_date', E::ts('Member Since'), [], false, ['time' => false]);
    $this->add('datepicker', 'start_date', E::ts('Start Date'), [], true, ['time' => false]);
    $this->add('datepicker', 'end_date', E::ts('End Date'), [], false, ['time' => false, 'placeholder' => E::ts('if already known')]);
    $this->add('select', 'campaign_id', E::ts('Campaign'), CRM_Contract_Configuration::getCampaignList(), false, ['class' => 'crm-select2']);

    // Membership type (membership)
    $MembershipTypeOptions = [];
    foreach(civicrm_api3('MembershipType', 'get', ['is_active' => 1, 'options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $MembershipType){
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    }
    $this->add(
      'select',
      'membership_type_id',
      E::ts('Membership type'),
      ['' => '- none -'] + $MembershipTypeOptions,
      true,
      ['class' => 'crm-select2']
    );

    // Source media (activity)
    foreach(civicrm_api3('Activity', 'getoptions', ['field' => "activity_medium_id", 'options' => ['limit' => 0, 'sort' => 'weight']])['values'] as $key => $value){
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', E::ts('Source media'), ['' => '- none -'] + $mediumOptions, false, ['class' => 'crm-select2']);

    // Reference number text
    $this->add('text', 'membership_reference', E::ts('Reference number'));

    // Contract number text
    $this->add(
      'text',
      'membership_contract',
      E::ts('Membership Number'),
      ['size' => 32, 'style' => 'text-align:left;'],
      false
    );

    // DD-Fundraiser
    $this->addEntityRef(
      'membership_dialoger',
      E::ts('Fundraiser'),
      ['api' => ['params' => ['contact_type' => 'Individual', 'contact_sub_type' => 'Dialoger']]]
    );

    // Membership channel
    $membershipChannelOptions = [];
    foreach(civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contact_channel',
      'is_active'       => 1,
      'options'         => ['limit' => 0, 'sort' => 'weight']])['values'] as $optionValue){
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    }
    $this->add(
      'select',
      'membership_channel',
      E::ts('Membership channel'),
      ['' => '- none -'] + $membershipChannelOptions,
      false,
      ['class' => 'crm-select2']);


    // Notes
    $this->add('wysiwyg', 'activity_details', E::ts('Notes'));

    // add the JS file for the payment preview
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/contract_modify_tools.js');

    $this->addButtons([
      ['type' => 'cancel', 'name' => E::ts('Cancel'), 'submitOnce' => TRUE],
      ['type' => 'submit', 'name' => E::ts('Create'), 'submitOnce' => TRUE],
    ]);

    $this->setDefaults();
  }

  /**
   * Set some default values in the form
   * @param $defaultValues
   * @param $filter
   * @return void
   * @throws Exception
   */
  function setDefaults($defaultValues = null, $filter = null) {

    // start date is now
    $defaults['start_date'] = date('Y-m-d');

    // sepa defaults
    $defaults['payment_frequency'] = '12'; // monthly
    $defaults['payment_option'] = 'create';
    $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
    $defaults['contact_id'] = $this->cid;

    ContractFormDefaultsEvent::adjustDefaults($defaults, 'create');

    parent::setDefaults($defaults);
  }

  /**
   * form validation
   */
  function validate() {
    $submitted = $this->exportValues();

    // check if the reference is not in use
    if (!empty($submitted['membership_contract'])) {
      $contract_number_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber($submitted['membership_contract']);
      if ($contract_number_error) {
        $this->setElementError('membership_contract', $contract_number_error);
      }
    }

    // check if an amount is necessary
    if (!in_array($submitted['payment_option'], ['existing', 'nochange', 'select'])) {
      if (empty($submitted['payment_amount'])) {
        $this->setElementError('payment_amount', "Please enter an amount");
      }
    }

    // check if all values for 'create new mandate' are there
    if ($submitted['payment_option'] == 'create') {
      if(empty($submitted['payment_frequency'])) {
        $this->setElementError ( 'payment_frequency', "Please enter a frequency");
      }

       $amount = CRM_Contract_SepaLogic::formatMoney((float) ($submitted['payment_amount'] / $submitted['payment_frequency']));
       if ($amount < 0.01) {
         $this->setElementError ( 'payment_amount', 'Annual amount too small.');
       }

      // format IBAN and BIC
      if (isset($this->_submitValues['iban'])) {
        $submitted['iban'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['iban']);
        $this->_submitValues['iban'] = $submitted['iban'];
      }
      if (isset($this->_submitValues['bic'])) {
        $submitted['bic'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['bic']);
        $this->_submitValues['bic'] = $submitted['bic'];
      }

      // SEPA validation
      if (empty($submitted['iban'])) {
        $this->setElementError ( 'iban', 'Die IBAN wird benötigt');
      }

      if (empty($submitted['bic'])) {
        $submitted['bic'] = 'NOTPROVIDED';
      }

      if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
        $this->setElementError ( 'iban', 'invalid IBAN');
      }
      if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
        $this->setElementError ( 'iban', "Pleas don't use the organisation's IBAN");
      }
      if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
        $this->setElementError ( 'bic', 'Please enter a valid BIC.');
      }
    }

    // check times
    if (!empty($submitted['join_date'])) {
      if (CRM_Utils_Date::processDate(date('Ymd')) < CRM_Utils_Date::processDate($submitted['join_date'])) {
        $this->setElementError('join_date', ts('Join date cannot be in the future.'));
      }
      if (CRM_Utils_Date::processDate($submitted['start_date']) < CRM_Utils_Date::processDate($submitted['join_date'])) {
        $this->setElementError('join_date', ts('Join date cannot after the start date.'));
      }
    }

    return parent::validate();
  }


  function postProcess() {
    $submitted = $this->exportValues();

    // a payment contract (recurring contribution) should be created - calculate some generic stuff
    if (empty($submitted['cycle_day']) || $submitted['cycle_day'] < 1 || $submitted['cycle_day'] > 30) {
      // invalid cycle day
      $submitted['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
    }

    // add the -technically correct- placeholder BIC
    if (empty($submitted['bic'])) {
      $submitted['bic'] = 'NOTPROVIDED';
    }

    // calculate amount
    // $annual_amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_frequency'] * CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']));
    $frequency_interval = (int) (12 / $submitted['payment_frequency']);
    $amount = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount']);
    $payment_contract = [];

    // SWITCH: contract creation/selection differs on the slected option
    switch ($submitted['payment_option']) {
      case '':   // CREATE NEW SEPA MANDATE
        $payment_contract = CRM_Contract_SepaLogic::createNewMandate([
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
          'financial_type_id'  => 2,  // Membership Dues
          'frequency_unit'     => 'month',
          'cycle_day'          => $submitted['cycle_day'],
          'frequency_interval' => $frequency_interval,
        ]);
        break;

      case 'existing':  // SELECT EXISTING PAYMENT CONTRACT
        $payment_contract['id'] = $submitted['recurring_contribution'];
        break;

      case 'nochange':  // NO CONTRACT CHANGES
        unset($payment_contract['id']);
        break;

      default:       // CREATE NEW PAYMENT CONTRACT for the other non-SEPA payment options like Cash or EFT
        // new contract
        $payment_contract_params = [
          'contact_id'            => $this->get('cid'),
          'amount'                => $amount,
          'currency'              => CRM_Contract_SepaLogic::getCreditor()->currency,
          'start_date'            => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
          'create_date'           => date('YmdHis'), // NOW
          'date'                  => CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s'),
          'validation_date'       => date('YmdHis'), // NOW
//                    'iban'            => $submitted['iban'],
//                    'bic'             => $submitted['bic'],
          'account_holder'      => $submitted['account_holder'],
          // 'source'             => ??
          'campaign_id'           => $submitted['campaign_id'] ?? '',
          'payment_instrument_id' => CRM_Contract_Configuration::getPaymentInstrumentIdByName($submitted['payment_option']),
          'financial_type_id'     => 2,  // Membership Dues
          'frequency_unit'        => 'month',
          'cycle_day'             => $submitted['cycle_day'],
          'frequency_interval'    => $frequency_interval,
          'checkPermissions'      => TRUE,
        ];
        CRM_Contract_CustomData::resolveCustomFields($payment_contract_params);
        $new_recurring_contribution = civicrm_api3('ContributionRecur', 'create', $payment_contract_params);
        $payment_contract['id'] = $new_recurring_contribution['id'];
        break;

    }

    // NOW CREATE THE CONTRACT (MEMBERSHIP)

    // Core fields
    $params['contact_id'] = $this->get('cid');
    $params['membership_type_id'] = $submitted['membership_type_id'];
    $params['start_date'] = CRM_Utils_Date::processDate($submitted['start_date'], null, null, 'Y-m-d H:i:s');
    $params['join_date'] = CRM_Utils_Date::processDate($submitted['join_date'], null, null, 'Y-m-d H:i:s');
    if($submitted['end_date']){
      $params['end_date'] = CRM_Utils_Date::processDate($submitted['end_date'], null, null, 'Y-m-d H:i:s');
    }
    $params['campaign_id'] = $submitted['campaign_id'] ?? '';

    // 'Custom' fields
    $params['membership_general.membership_reference']       = $submitted['membership_reference'] ?? ''; // Reference number
    $params['membership_general.membership_contract']        = $submitted['membership_contract'];  // Contract number
    $params['membership_general.membership_dialoger']        = $submitted['membership_dialoger'];  // DD fundraiser
    $params['membership_general.membership_channel']         = $submitted['membership_channel'] ?? '';   // Membership Channel
    $params['membership_general.membership_notes']           = $submitted['activity_details'];      // Membership Channel

    // add payment contract
    $params['membership_payment.membership_recurring_contribution'] = $payment_contract['id'] ?? null; // Recurring contribution
    $params['membership_payment.from_name'] = $submitted['account_holder'];
    $params['note'] = $submitted['activity_details'];
    $params['medium_id'] = $submitted['activity_medium'] ?? '';

    CRM_Contract_CustomData::resolveCustomFields($params);
    $membershipResult = civicrm_api3('Contract', 'create', $params);

    // update and redirect
    $this->ajaxResponse['updateTabs']['#tab_sepa'] = 1;
    $this->ajaxResponse['updateTabs']['#tab_member'] = 1;
    $this->controller->setDestination(CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$this->get('cid')}"));
  }

  /**
   * Get the list of eligible payment options
   *
   * @return array
   */
  public function getPaymentOptions() {
    return CRM_Contract_Configuration::getPaymentOptions(true, false);
  }
}
