<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2024 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Modify extends CRM_Core_Form {

  /**
   * @var ?string the modify action */
  protected ?string $modify_action = NULL;

  /**
   * the membership data */
  protected array $membership;

  protected string $change_class;

  protected array $contact;

  public function preProcess() {
    parent::preProcess();

    // If we requested a contract file download
    $download = CRM_Utils_Request::retrieve('ct_dl', 'String', NULL, FALSE, '', 'GET');
    if (!empty($download)) {
      // FIXME: Could use CRM_Utils_System::download but it still requires you to do all the work (load file to stream
      //        etc) before calling.
      if (CRM_Contract_Utils::downloadContractFile($download)) {
        CRM_Utils_System::civiExit();
      }
      // If the file didn't exist
      echo 'File does not exist';
      CRM_Utils_System::civiExit();
    }

    // Not sure why this isn't simpler but here is my way of ensuring that the
    // id parameter is available throughout this forms life
    $this->id = CRM_Utils_Request::retrieve('id', 'Integer');
    if ($this->id) {
      $this->set('id', $this->id);
    }
    if (!$this->get('id')) {
      throw new CRM_Core_Exception(E::ts('Missing the contract ID'));
    }

    // Set a message when updating a contract if scheduled updates already exist
    $modifications = civicrm_api3('Contract', 'get_open_modification_counts', ['id' => $this->get('id')])['values'];
    if ($modifications['scheduled'] || $modifications['needs_review']) {
      // phpcs:disable Generic.Files.LineLength.TooLong
      CRM_Core_Session::setStatus(
        E::ts(
          'Some updates have already been scheduled for this contract. Please ensure that this new update will not conflict with existing updates'
        ),
        E::ts('Scheduled updates exist!'),
        'alert',
        ['expires' => 0]
      );
      // phpcs:enable
    }

    // Load the the contract to populate default form values
    try {
      $this->membership = civicrm_api3('Membership', 'getsingle', ['id' => $this->get('id')]);
    }
    catch (Exception $e) {
      CRM_Core_Error::fatal('Not a valid contract ID');
    }

    // Process the requested action
    $this->modify_action = strtolower(CRM_Utils_Request::retrieve('modify_action', 'String'));
    $this->assign('modificationActivity', $this->modify_action);
    $this->change_class = CRM_Contract_Change::getClassByAction($this->modify_action);
    if (empty($this->change_class)) {
      throw new Exception(E::ts("Unknown action '%1'.", [1 => $this->modify_action]));
    }

    // set title
    CRM_Utils_System::setTitle($this->change_class::getChangeTitle());

    // Set the destination for the form
    $this->controller->_destination = CRM_Utils_System::url(
      'civicrm/contact/view',
      "reset=1&cid={$this->membership['contact_id']}&selectedChild=member"
    );

    // Assign the contact id (necessary for the mandate popup)
    $this->assign('cid', $this->membership['contact_id']);

    // check if BIC lookup is possible
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // assign current cycle day
    $current_cycle_day = CRM_Contract_RecurringContribution::getCycleDay(
      $this->membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')]
    );
    $this->assign('current_cycle_day', $current_cycle_day);

    // Validate that the contract has a valid start status
    $membershipStatus = CRM_Contract_Utils::getMembershipStatusName($this->membership['status_id']);
    if (!in_array($membershipStatus, $this->change_class::getStartStatusList())) {
      throw new Exception(E::ts("Invalid modification for status '%1'.", [1 => $membershipStatus]));
    }
  }

  public function buildQuickForm() {

    // Add fields that are present on all contact history forms

    // Add the date that this update should take effect (leave blank for now)
    $this->add('datepicker', 'activity_date', E::ts('Schedule date'), [], FALSE,
      ['time' => FALSE, 'placeholder' => E::ts('now')]);

    // Add the interaction medium
    $mediumOptions = [];
    foreach (civicrm_api3('Activity', 'getoptions', [
      'field' => 'activity_medium_id',
      'options' => ['limit' => 0, 'sort' => 'weight'],
    ])['values'] as $key => $value) {
      $mediumOptions[$key] = $value;
    }
    $this->add('select', 'activity_medium', E::ts('Source media'),
      ['' => '- none -'] + $mediumOptions, FALSE, ['class' => 'crm-select2']);

    // Add a note field
    $this->add('wysiwyg', 'activity_details', E::ts('Notes'));

    // Then add fields that are dependent on the action
    if (in_array($this->modify_action, ['update', 'revive'])) {
      $this->addUpdateFields();
    }
    elseif ($this->modify_action == 'cancel') {
      $this->addCancelFields();
    }
    elseif ($this->modify_action == 'pause') {
      $this->addPauseFields();
    }

    // add the JS file for the payment preview
    CRM_Core_Resources::singleton()->addScriptFile('de.systopia.contract', 'js/contract_modify_tools.js');

    $this->addButtons([
    // since Cancel looks bad when viewed next to the Cancel action
      ['type' => 'cancel', 'name' => E::ts('Discard changes'), 'submitOnce' => TRUE],
      ['type' => 'submit', 'name' => $this->change_class::getChangeTitle(), 'isDefault' => TRUE, 'submitOnce' => TRUE],
    ]);

    $this->setDefaults();
  }

  /**
   * Note: also used for revive
   */
  public function addUpdateFields() {

    // load contact
    if (empty($this->membership['contact_id'])) {
      $this->contact = ['display_name' => 'Error'];
    }
    else {
      $this->contact = civicrm_api3('Contact', 'getsingle', [
        'id'     => $this->membership['contact_id'],
        'return' => 'display_name',
      ]);
    }

    // load current contract
    $current_contract = CRM_Contract_RecurringContribution::getCurrentContract(
      $this->membership['contact_id'],
      $this->membership[CRM_Contract_Utils::getCustomFieldId('membership_payment.membership_recurring_contribution')]
    );
    // JS for the pop up
    CRM_Core_Resources::singleton()->addVars(
      'de.systopia.contract',
      [
        'cid' => $this->membership['contact_id'],
        'current_recurring' => $this->membership[CRM_Contract_Utils::getCustomFieldId(
          'membership_payment.membership_recurring_contribution'
        )],
        'debitor_name' => $this->contact['display_name'],
        'creditor' => CRM_Contract_SepaLogic::getCreditor(),
        'frequencies' => CRM_Contract_SepaLogic::getPaymentFrequencies(),
        'grace_end' => CRM_Contract_SepaLogic::getNextInstallmentDate(
          $this->membership[CRM_Contract_Utils::getCustomFieldId(
            'membership_payment.membership_recurring_contribution'
          )]
        ),
        'action' => $this->modify_action,
        'current_contract' => $current_contract,
        'recurring_contributions' => CRM_Contract_RecurringContribution::getAllForContact(
          $this->membership['contact_id'],
          TRUE,
          $this->get('id')
        ),
      ]
    );

    // pass the current_contract_amount
    $this->addElement(
      'hidden',
      'current_contract_amount',
      $current_contract['fields']['amount'] ?? 0
    );

    // add the JS tools
    CRM_Contract_SepaLogic::addJsSepaTools();

    // add a generic switch to clean up form
    $payment_options = [
      'modify'   => E::ts('modify contract'),
      'select'   => E::ts('select other'),
    ];

    // add 'new contract' options
    $payment_options = CRM_Contract_Configuration::getPaymentOptions(TRUE, TRUE);

    $this->add(
      'select',
      'payment_option',
      E::ts('Payment'),
      $payment_options,
      TRUE,
      ['class' => 'crm-select2']
    );

    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2(
      'recurring_contribution',
      $this->membership['contact_id'],
      FALSE,
      $this->get('id')
    );

    // Membership type (membership)
    foreach (civicrm_api3(
      'MembershipType',
      'get',
      ['options' => ['limit' => 0, 'sort' => 'weight']]
    )['values'] as $MembershipType) {
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    }
    $this->add(
      'select',
      'membership_type_id',
      E::ts('Membership type'),
      ['' => '- none -'] + $MembershipTypeOptions,
      TRUE,
      ['class' => 'crm-select2']
    );

    // Campaign
    $this->add(
      'select',
      'campaign_id',
      E::ts('Campaign'),
      CRM_Contract_Configuration::getCampaignList(),
      FALSE,
      ['class' => 'crm-select2']
    );

    $this->add(
      'select',
      'cycle_day',
      E::ts('Cycle day'),
      CRM_Contract_SepaLogic::getCycleDays(),
      FALSE,
      ['class' => 'crm-select2']
    );
    $this->add(
      'text',
      'iban',
      E::ts('IBAN'),
      ['class' => 'huge']
    );
    $this->add(
      'text',
      'bic',
      E::ts('BIC')
    );
    $this->add(
      'text',
      'account_holder',
      E::ts('Account Holder'),
      ['class' => 'huge']
    );
    $this->add(
      'text',
      'payment_amount',
      E::ts('Installment Amount'),
      ['size' => 6]
    );
    $this->add(
      'select',
      'payment_frequency',
      E::ts('Payment Frequency'),
      CRM_Contract_SepaLogic::getPaymentFrequencies(),
      FALSE,
      ['class' => 'crm-select2']
    );
    $this->add(
      'select',
      'defer_payment_start',
      E::ts('Start Collection'),
      [
        0 => E::ts('as soon as possible'),
        1 => E::ts('respect previous cycle'),
      ],
      FALSE,
      ['class' => 'crm-select2']
    );
  }

  public function addCancelFields() {

    // Cancel reason
    foreach (civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contract_cancel_reason',
      'filter'          => 0,
      'is_active'       => 1,
      'options'         => ['limit' => 0, 'sort' => 'weight'],
    ])['values'] as $cancelReason) {
      $cancelOptions[$cancelReason['value']] = $cancelReason['label'];
    }
    $this->addRule('activity_date', 'Scheduled date is required for a cancellation', 'required');
    $this->add(
      'select',
      'cancel_reason',
      E::ts('Cancellation reason'),
      ['' => '- none -'] + $cancelOptions,
      TRUE,
      ['class' => 'crm-select2 huge']
    );
  }

  public function addPauseFields() {

    // Resume date
    $this->add(
      'datepicker',
      'resume_date',
      E::ts('Resume Date'),
      TRUE,
      ['time' => FALSE, 'formatType' => 'activityDate']
    );
  }

  public function setDefaults($defaultValues = NULL, $filter = NULL) {
    $recurring_contribution_id_field = CRM_Contract_Utils::getCustomFieldId(
      'membership_payment.membership_recurring_contribution'
    );
    if (isset($this->membership[$recurring_contribution_id_field])) {
      $defaults['payment_option'] = 'nochange';
      $defaults['recurring_contribution'] = $this->membership[$recurring_contribution_id_field];
      // wait until the paid-for time has passed
      $defaults['defer_payment_start'] = 1;

      // set cycle day
      if (empty($defaults['recurring_contribution'])) {
        // no previous contract given
        $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
      }
      else {
        // take cycle day from previous contract
        $defaults['cycle_day'] = \civicrm_api3('ContributionRecur', 'getvalue', [
          'id' => (int) $defaults['recurring_contribution'],
          'return' => 'cycle_day',
        ]);
      }
      $defaults['payment_frequency'] = $this->membership[CRM_Contract_Utils::getCustomFieldId(
        'membership_payment.membership_frequency'
      )] ?? 12;
      // Back Office
      $defaults['activity_medium'] = '7';
    }

    $defaults['membership_type_id'] = $this->membership['membership_type_id'];

    if ($this->modify_action == 'cancel') {
      [$defaults['activity_date'], $defaults['activity_date_time']] = CRM_Utils_Date::setDateDefaults(
        date('Y-m-d H:i:00'),
        'activityDateTime'
      );
    }
    else {
      // if it's not a cancellation, set the default change date to tomorrow 12am (see GP-1507)
      [$defaults['activity_date'], $defaults['activity_date_time']] = CRM_Utils_Date::setDateDefaults(
        date('Y-m-d 00:00:00', strtotime('+1 day')),
        'activityDateTime'
      );
    }

    // add customisation
    \Civi\Contract\Event\ContractFormDefaultsEvent::adjustDefaults($defaults, $this->modify_action);

    parent::setDefaults($defaults);
  }

  public function validate() {
    $submitted = $this->exportValues();
    if (empty($submitted['activity_date'])) {
      $submitted['activity_date'] = date('Y-m-d');
    }
    $activityDate = CRM_Utils_Date::processDate($submitted['activity_date'], $submitted['activity_date_time']);
    $midnightThisMorning = date('Ymd000000');
    if ($activityDate < $midnightThisMorning) {
      HTML_QuickForm::setElementError(
        'activity_date',
        'Activity date must be either today (which will execute the change now) or in the future'
      );
    }
    if ($this->modify_action == 'pause') {
      $resumeDate = CRM_Utils_Date::processDate($submitted['resume_date']);

      if ($activityDate > $resumeDate) {
        HTML_QuickForm::setElementError('resume_date', 'Resume date must be after the scheduled pause date');
      }
    }

    if (isset($submitted['payment_option']) && $submitted['payment_option'] == 'modify') {
      if ($submitted['payment_amount'] && !$submitted['payment_frequency']) {
        HTML_QuickForm::setElementError('payment_frequency', 'Please specify a frequency when specifying an amount');
      }
      if ($submitted['payment_frequency'] && !$submitted['payment_amount']) {
        // empty values are ok, will later be filled with previous values
        if ('' !== trim($submitted['payment_amount'])) {
          HTML_QuickForm::setElementError('payment_amount', 'Please specify an amount when specifying a frequency');
        }
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
      if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
        HTML_QuickForm::setElementError('iban', 'Please enter a valid IBAN');
      }
      if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
        HTML_QuickForm::setElementError('iban', "Do not use any of the organisation's own IBANs");
      }
      if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
        HTML_QuickForm::setElementError('bic', 'Please enter a valid BIC');
      }
    }

    return parent::validate();
  }

  public function postProcess() {

    // Construct a call to contract.modify
    // The following fields to be submitted in all cases
    $submitted = $this->exportValues();
    $params['id'] = $this->get('id');
    $params['action'] = $this->modify_action;
    $params['medium_id'] = $submitted['activity_medium'];
    $params['note'] = $submitted['activity_details'];

    //If the date was set, convert it to the necessary format
    if ($submitted['activity_date']) {
      $params['date'] = CRM_Utils_Date::processDate(
        $submitted['activity_date'],
        $submitted['activity_date_time'],
        FALSE,
        'Y-m-d H:i:s'
      );
    }

    // If this is an update or a revival
    if (in_array($this->modify_action, ['update', 'revive'])) {

      // now add the payment
      switch ($submitted['payment_option']) {
        // select a new recurring contribution
        case 'select':
          $params['membership_payment.membership_recurring_contribution'] = (int) $submitted['recurring_contribution'];
          break;

        case 'nochange':
          // anything to do here?
          break;

        default:
          // a new payment option is picked
          $new_payment_option = $submitted['payment_option'];
          $payment_types = CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);
          $payment_instrument_id = $payment_types[$new_payment_option] ?? '';
          if ($payment_instrument_id) {
            $params['membership_payment.payment_instrument'] = $payment_instrument_id;
          }

          // compile other change data
          $params['membership_payment.membership_annual'] = CRM_Contract_SepaLogic::formatMoney(
            $submitted['payment_frequency'] * CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'])
          );
          $params['membership_payment.membership_frequency'] = $submitted['payment_frequency'];
          $params['membership_payment.cycle_day'] = $submitted['cycle_day'];
          $params['membership_payment.to_ba'] = CRM_Contract_BankingLogic::getCreditorBankAccount();
          $params['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount(
            $this->membership['contact_id'],
            $submitted['iban'],
            $submitted['bic']
          );
          $params['membership_payment.from_name'] = $submitted['account_holder'];
          $params['membership_payment.defer_payment_start'] = empty($submitted['defer_payment_start']) ? '0' : '1';
          break;
      }

      // add other changes
      $params['membership_type_id'] = $submitted['membership_type_id'];
      $params['campaign_id'] = $submitted['campaign_id'];

      // If this is a cancellation
    }
    elseif ($this->modify_action == 'cancel') {
      $params['membership_cancellation.membership_cancel_reason'] = $submitted['cancel_reason'];

      // If this is a pause
    }
    elseif ($this->modify_action == 'pause') {
      $params['resume_date'] = CRM_Utils_Date::processDate($submitted['resume_date'], FALSE, FALSE, 'Y-m-d');
    }

    CRM_Contract_CustomData::resolveCustomFields($params);
    civicrm_api3('Contract', 'modify', $params);
    civicrm_api3('Contract', 'process_scheduled_modifications', ['id' => $params['id']]);
  }

  /**
   * this is just a crutch since civistrings doesn't pick up those strings in my .js files
   * TODO: Check if this is still relevant with current versions of civistrings.
   */
  public function _expose_missing_js_translations() {
    E::ts('Debitor name');
    E::ts('Debitor account');
    E::ts('Creditor name');
    E::ts('Creditor account');
    E::ts('Payment method: SEPA Direct Debit');
    E::ts('Frequency');
    E::ts('Annual amount');
    E::ts('Installment amount');
    E::ts('Next debit');
    E::ts('Start Collection');
    E::ts('as soon as possible');
    E::ts('respect previous cycle');
  }

}
