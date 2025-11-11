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

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Create extends CRM_Core_Form {

  /**
   * @var ?int contact ID */
  protected ?int $cid;

  /**
   * @var array contact  data on the membership's contact */
  protected array $contact;

  public function buildQuickForm() {
    $this->cid = CRM_Utils_Request::retrieve('cid', 'Integer');
    if (empty($this->cid)) {
      $this->cid = $this->get('cid');
    }
    if ($this->cid) {
      $this->set('cid', $this->cid);
    }
    else {
      CRM_Core_Error::statusBounce('You have to specify a contact ID to create a new contract');
    }
    $this->controller->_destination = CRM_Utils_System::url(
      'civicrm/contact/view',
      "reset=1&cid={$this->get('cid')}&selectedChild=member"
    );

    $this->assign('cid', $this->get('cid'));
    $this->contact = civicrm_api3('Contact', 'getsingle', ['id' => $this->get('cid')]);
    $this->assign('contact', $this->contact);
    self::setTitle(E::ts('Create a new contract for %1', [1 => $this->contact['display_name']]));

    $formUtils = new CRM_Contract_FormUtils($this, 'Membership');
    $formUtils->addPaymentContractSelect2('recurring_contribution', $this->get('cid'), FALSE, NULL);
    CRM_Core_Resources::singleton()->addVars(
      'contract',
      [
        'cid' => $this->get('cid'),
        'debitor_name' => $this->contact['display_name'],
        'creditor' => CRM_Contract_SepaLogic::getCreditor(),
        'frequencies' => CRM_Contract_SepaLogic::getPaymentFrequencies(),
        'grace_end' => NULL,
        'recurring_contributions' => CRM_Contract_RecurringContribution::getAllForContact(
          $this->get('cid'), TRUE, NULL, TRUE
        ),
      ]
    );

    CRM_Contract_SepaLogic::addJsSepaTools();

    // Payment dates
    $this->add(
      'select',
      'payment_option',
      E::ts('Payment'),
      $this->getPaymentOptions(),
      TRUE,
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
      E::ts('BIC'),
      ['class' => 'normal', 'placeholder' => 'NOTPROVIDED'],
      FALSE
    );
    $this->add(
      'text',
      'account_holder',
      E::ts('Members Bank Account'),
      ['class' => 'huge', 'placeholder' => $this->contact['display_name']]
    );
    $this->add(
      'text',
      'payment_amount',
      E::ts('Installment amount'),
      ['size' => 6, 'placeholder' => E::ts('Installment')]
    );
    $this->add(
      'select',
      'payment_frequency',
      E::ts('Payment Frequency'),
      CRM_Contract_SepaLogic::getPaymentFrequencies(),
      FALSE,
      ['class' => 'crm-select2']
    );
    $this->assign('bic_lookup_accessible', CRM_Contract_SepaLogic::isLittleBicExtensionAccessible());

    // Contract dates
    $this->add(
      'datepicker',
      'join_date',
      E::ts('Member Since'),
      [],
      FALSE,
      ['time' => FALSE]
    );
    $this->add(
      'datepicker',
      'start_date',
      E::ts('Start Date'),
      [],
      TRUE,
      ['time' => FALSE]
    );
    $this->add(
      'datepicker',
      'end_date',
      E::ts('End Date'),
      [],
      FALSE,
      ['time' => FALSE, 'placeholder' => E::ts('if already known')]
    );
    $this->add(
      'select',
      'campaign_id',
      E::ts('Campaign'),
      CRM_Contract_Configuration::getCampaignList(),
      FALSE,
      ['class' => 'crm-select2']
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

    // Source media (activity)
    foreach (civicrm_api3(
      'Activity',
      'getoptions',
      ['field' => 'activity_medium_id', 'options' => ['limit' => 0, 'sort' => 'weight']]
    )['values'] as $key => $value) {
      $mediumOptions[$key] = $value;
    }
    $this->add(
      'select',
      'activity_medium',
      E::ts('Source media'),
      ['' => E::ts('- none -')] + $mediumOptions,
      FALSE,
      ['class' => 'crm-select2']
    );

    // Reference number text
    $this->add(
      'text',
      'membership_reference',
      E::ts('Reference number')
    );

    // Contract number text
    $this->add(
      'text',
      'membership_contract',
      E::ts('Membership Number'),
      ['size' => 32, 'style' => 'text-align:left;'],
      FALSE
    );

    // Membership channel
    $membershipChannelOptions = [];
    foreach (civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contact_channel',
      'is_active'       => 1,
      'options'         => ['limit' => 0, 'sort' => 'weight'],
    ])['values'] as $optionValue) {
      $membershipChannelOptions[$optionValue['value']] = $optionValue['label'];
    }
    $this->add(
      'select',
      'membership_channel',
      E::ts('Membership channel'),
      ['' => E::ts('- none -')] + $membershipChannelOptions,
      FALSE,
      ['class' => 'crm-select2']);

    // Notes
    $this->add('wysiwyg', 'activity_details', E::ts('Notes'));

    // add the JS file for the payment preview
    CRM_Core_Resources::singleton()->addScriptFile(E::LONG_NAME, 'js/contract_modify_tools.js');

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
  public function setDefaults($defaultValues = NULL, $filter = NULL) {

    // start date is now
    $defaults['start_date'] = date('Y-m-d');

    // sepa defaults
    $defaults['payment_frequency'] = '12';
    $defaults['payment_option'] = 'create';
    $defaults['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
    $defaults['contact_id'] = $this->cid;

    \Civi\Contract\Event\ContractFormDefaultsEvent::adjustDefaults($defaults, 'create');

    parent::setDefaults($defaults);
  }

  /**
   * form validation
   */
  // phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Drupal.WhiteSpace.ScopeIndent.IncorrectExact
  public function validate() {
  // phpcs:enable
    $submitted = $this->exportValues();

    // check if the reference is not in use
    if (!empty($submitted['membership_contract'])) {
      $contract_number_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber(
        $submitted['membership_contract']
      );
      if ($contract_number_error) {
        $this->setElementError('membership_contract', $contract_number_error);
      }
    }

    $mode = $submitted['payment_option'] ?? '';

    if (!in_array($mode, ['existing', 'nochange', 'select', 'None'], TRUE)) {
      if (!array_key_exists('payment_amount', $submitted) || trim((string) $submitted['payment_amount']) === '') {
        $this->setElementError('payment_amount', 'Please enter an amount');
      }
    }

    switch ($mode) {
      case 'RCUR':
        if (empty($submitted['payment_frequency'])) {
          $this->setElementError('payment_frequency', 'Please enter a frequency');
        }
        $cycle = (int) ($submitted['cycle_day'] ?? 0);
        if ($cycle < 1 || $cycle > 30) {
          $cycle = CRM_Contract_SepaLogic::nextCycleDay();
          $this->_submitValues['cycle_day'] = $cycle;
        }
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
          $this->setElementError('iban', 'Please enter an IBAN');
        }
        elseif (!CRM_Contract_SepaLogic::validateIBAN($submitted['iban'])) {
          $this->setElementError('iban', 'Please enter a valid IBAN');
        }
        elseif (CRM_Contract_SepaLogic::isOrganisationIBAN($submitted['iban'])) {
          $this->setElementError('iban', "Do not use any of the organisation's own IBANs");
        }
        if (!empty($submitted['bic']) && !CRM_Contract_SepaLogic::validateBIC($submitted['bic'])) {
          $this->setElementError('bic', 'Please enter a valid BIC');
        }
        $amountNum = CRM_Contract_SepaLogic::formatMoney($submitted['payment_amount'] ?? 0);
        if ($amountNum <= 0) {
          $this->setElementError('payment_amount', 'Amount must be greater than 0 for SEPA');
        }
        break;

      case 'select':
        if (empty($submitted['recurring_contribution'])) {
          $this->setElementError('recurring_contribution', 'Please select a recurring contribution');
        }
        break;

      case 'None':
        break;

      default:
        if (empty($submitted['payment_frequency'])) {
          $this->setElementError('payment_frequency', 'Please enter a frequency');
        }
        break;
    }

    if (!empty($submitted['join_date'])) {
      if (CRM_Utils_Date::processDate(date('Ymd')) < CRM_Utils_Date::processDate($submitted['join_date'])) {
        $this->setElementError('join_date', ts('Join date cannot be in the future.'));
      }
      if (
        CRM_Utils_Date::processDate($submitted['start_date']) < CRM_Utils_Date::processDate($submitted['join_date'])
      ) {
        $this->setElementError('join_date', ts('Join date cannot be after the start date.'));
      }
    }

    return parent::validate();
  }

  public function postProcess() {
    /** @var int $contactId */
    $contactId = $this->get('cid');
    $submitted = $this->exportValues();

    $params = ['contact_id' => $contactId]
      + array_intersect_key(
        $submitted,
        array_flip(
          [
            'membership_type_id',
            'start_date',
            'join_date',
            'end_date',
            'membership_reference',
            'membership_contract',
            'membership_channel',
            'payment_option',
            'recurring_contribution',
            'payment_amount',
            'cycle_day',
            'payment_frequency',
            'iban',
            'bic',
            'account_holder',
            'campaign_id',
          ]
        )
      );
    \Civi\Api4\Contract::createfull()
      ->setValues($params)
      ->execute();

    // update and redirect
    CRM_Contract_FormUtils::updateContactSummaryTabs($this);
    $this->controller->setDestination(
      CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contactId}")
    );
  }

  /**
   * Get the list of eligible payment options
   *
   * @return array
   */
  public function getPaymentOptions() {
    return CRM_Contract_Configuration::getPaymentOptions(TRUE, FALSE);
  }

}
