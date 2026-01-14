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

  protected ?int $id = NULL;

  protected ?string $modify_action = NULL;

  protected ?array $membership = NULL;

  protected ?string $change_class = NULL;

  protected ?array $contact = NULL;

  public function preProcess() {
    parent::preProcess();

    // If we requested a contract file download
    $store = NULL;
    $download = CRM_Utils_Request::retrieve('ct_dl', 'String', $store, FALSE, '', 'GET');
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
    $this->id = $this->get('id') ?? CRM_Utils_Request::retrieve('id', 'Integer');
    $this->set('id', $this->id);
    if (!isset($this->id)) {
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
      throw new \RuntimeException('Not a valid contract ID', $e->getCode(), $e);
    }

    // Process the requested action
    $this->modify_action = $this->get('modify_action')
      ?? strtolower(CRM_Utils_Request::retrieve('modify_action', 'String'));
    $this->set('modify_action', $this->modify_action);
    $this->assign('modificationActivity', $this->modify_action);
    $this->change_class = CRM_Contract_Change::getClassByAction($this->modify_action);
    if (empty($this->change_class)) {
      throw new \RuntimeException(E::ts("Unknown action '%1'.", [1 => $this->modify_action]));
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
      throw new \RuntimeException("Invalid modification for status '{$membershipStatus}'.");
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
      ['' => E::ts('- none -')] + $mediumOptions, FALSE, ['class' => 'crm-select2']);

    // Add a note field
    $this->add('wysiwyg', 'activity_details', E::ts('Notes'));

    // Then add fields that are dependent on the action
    if (in_array($this->modify_action, ['update', 'revive'])) {
      $this->addUpdateFields();
    }
    elseif ('cancel' === $this->modify_action) {
      $this->addCancelFields();
    }
    elseif ('pause' === $this->modify_action) {
      $this->addPauseFields();
    }

    // add the JS file for the payment preview
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/contract_modify_tools.js');

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
      'contract',
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
    $MembershipTypeOptions = [];
    foreach (civicrm_api3(
      'MembershipType',
      'get',
      ['is_active' => 1, 'options' => ['limit' => 0, 'sort' => 'weight']]
    )['values'] as $MembershipType) {
      $MembershipTypeOptions[$MembershipType['id']] = $MembershipType['name'];
    }
    $this->add(
      'select',
      'membership_type_id',
      E::ts('Membership type'),
      ['' => E::ts('- none -')] + $MembershipTypeOptions,
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
    $cancelOptions = [];
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
      ['' => E::ts('- none -')] + $cancelOptions,
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
      [],
      TRUE,
      ['time' => FALSE, 'formatType' => 'activityDate']
    );
  }

  public function setDefaults($defaultValues = NULL, $filter = NULL) {
    $defaults = [];
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
        $defaults['payment_amount'] = \civicrm_api3('ContributionRecur', 'getvalue', [
          'id' => (int) $defaults['recurring_contribution'],
          'return' => 'amount',
        ]);
      }
      $defaults['payment_frequency'] = $this->membership[CRM_Contract_Utils::getCustomFieldId(
        'membership_payment.membership_frequency'
      )] ?? 12;
      // Back Office
      $defaults['activity_medium'] = '7';
    }

    $defaults['membership_type_id'] = $this->membership['membership_type_id'];
    $defaults['campaign_id'] = $this->membership['campaign_id'] ?? '';

    if ('cancel' === $this->modify_action) {
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

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded
  public function validate() {
  // phpcs:enable
    $submitted = $this->exportValues();
    if (empty($submitted['activity_date'])) {
      $submitted['activity_date'] = date('Y-m-d');
    }
    $activityDate = CRM_Utils_Date::processDate($submitted['activity_date'], $submitted['activity_date_time'] ?? NULL);
    $midnightThisMorning = date('Ymd000000');
    if ($activityDate < $midnightThisMorning) {
      HTML_QuickForm::setElementError('activity_date',
        'Activity date must be either today (which will execute the change now) or in the future');
    }
    if ('pause' === $this->modify_action) {
      $resumeDate = CRM_Utils_Date::processDate($submitted['resume_date']);
      if ($activityDate > $resumeDate) {
        HTML_QuickForm::setElementError('resume_date', 'Resume date must be after the scheduled pause date');
      }
    }

    if (!in_array($this->modify_action, ['update', 'revive'], TRUE)) {
      return parent::validate();
    }

    $mode = $submitted['payment_option'] ?? '';

    switch ($mode) {
      case 'modify':
        $amount = $submitted['payment_amount'] ?? '';
        $freq = $submitted['payment_frequency'] ?? '';
        if ($amount && !$freq) {
          HTML_QuickForm::setElementError('payment_frequency', 'Please specify a frequency when specifying an amount');
        }
        if ($freq && ('' !== trim((string) $amount)) && !$amount) {
          HTML_QuickForm::setElementError('payment_amount', 'Please specify an amount when specifying a frequency');
        }
        if (isset($this->_submitValues['iban'])) {
          $submitted['iban'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['iban']);
          $this->_submitValues['iban'] = $submitted['iban'];
        }
        if (isset($this->_submitValues['bic'])) {
          $submitted['bic'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['bic']);
          $this->_submitValues['bic'] = $submitted['bic'];
        }
        if (!empty($submitted['iban']) && !CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
          HTML_QuickForm::setElementError('iban', 'Please enter a valid IBAN');
        }
        if (!empty($submitted['iban']) && CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
          HTML_QuickForm::setElementError('iban', "Do not use any of the organisation's own IBANs");
        }
        if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
          HTML_QuickForm::setElementError('bic', 'Please enter a valid BIC');
        }
        break;

      case 'select':
        if (empty($submitted['recurring_contribution'])) {
          HTML_QuickForm::setElementError('recurring_contribution', 'Please select a recurring contribution');
        }
        break;

      case 'RCUR':
        if (empty($submitted['payment_amount'])) {
          HTML_QuickForm::setElementError('payment_amount', 'Please enter an amount');
        }
        if (empty($submitted['payment_frequency'])) {
          HTML_QuickForm::setElementError('payment_frequency', 'Please enter a frequency');
        }
        if (empty($submitted['cycle_day'])) {
          HTML_QuickForm::setElementError('cycle_day', 'Please select a cycle day');
        }
        if (isset($this->_submitValues['iban'])) {
          $submitted['iban'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['iban']);
          $this->_submitValues['iban'] = $submitted['iban'];
        }
        if (isset($this->_submitValues['bic'])) {
          $submitted['bic'] = CRM_Contract_SepaLogic::formatIBAN($this->_submitValues['bic']);
          $this->_submitValues['bic'] = $submitted['bic'];
        }
        if (empty($submitted['iban'])) {
          HTML_QuickForm::setElementError('iban', 'Please enter an IBAN');
        }
        elseif (!CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
          HTML_QuickForm::setElementError('iban', 'Please enter a valid IBAN');
        }
        elseif (CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
          HTML_QuickForm::setElementError('iban', "Do not use any of the organisation's own IBANs");
        }
        if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
          HTML_QuickForm::setElementError('bic', 'Please enter a valid BIC');
        }
        $amountNum = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'] ?? 0);
        if ($amountNum <= 0) {
          HTML_QuickForm::setElementError('payment_amount', 'Amount must be greater than 0 for SEPA');
        }
        break;

      case 'None':
      case 'nochange':
      case '':
        break;

      default:
        if (empty($submitted['payment_amount'])) {
          HTML_QuickForm::setElementError('payment_amount', 'Please enter an amount');
        }
        if (empty($submitted['payment_frequency'])) {
          HTML_QuickForm::setElementError('payment_frequency', 'Please enter a frequency');
        }
        break;
    }

    return parent::validate();
  }

  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function postProcess() {
  // phpcs:enable

    // Construct a call to contract.modify
    // The following fields to be submitted in all cases
    $submitted = $this->exportValues();
    $params = [
      'id' => $this->get('id'),
      'action' => $this->modify_action,
      'medium_id' => $submitted['activity_medium'],
      'note' => $submitted['activity_details'],
    ]
    + array_intersect_key(
      $submitted,
      array_flip(
        [
          'membership_type_id',
          'payment_option',
          'recurring_contribution',
          'iban',
          'bic',
          'payment_amount',
          'payment_frequency',
          'cycle_day',
          'account_holder',
          'defer_payment_start',
          'campaign_id',
          'cancel_reason',
          'resume_date',
        ]
      )
    );

    //If the date was set, convert it to the necessary format
    if ($submitted['activity_date']) {
      $params['date'] = CRM_Utils_Date::processDate(
        $submitted['activity_date'],
        $submitted['activity_date_time'],
        FALSE,
        'Y-m-d H:i:s'
      );
    }

    \Civi\Api4\Contract::modifyFull()
      ->setValues($params)
      ->execute();

    CRM_Contract_FormUtils::updateContactSummaryTabs($this);
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
