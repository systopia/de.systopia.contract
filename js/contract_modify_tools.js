/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2025 SYSTOPIA                             |
| Author: SYSTOPIA (info@systopia.de)                          |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

(function ($, ts) {
  'use strict';

  CRM.contract = {
    sepaTools: {
      sepaCreditorParameters: CRM.vars.contract.sepa_creditor_parameters,

      /**
       * formats a value to the CiviCRM failsafe format: 0.00 (e.g. 999999.90)
       * even if there are ',' in there, which are used in some countries
       * (e.g. Germany, Austria,) as a decimal point.
       * @see CRM_Contract_SepaLogic::formatMoney
       */
      parseMoney: function (raw_value) {
        if (raw_value.length == 0) {
          return 0.0;
        }

        // find out if there's a problem with ','
        let stripped_value = raw_value.replace(' ', '');
        if (stripped_value.includes(',')) {
          // if there are at least three digits after the ','
          //  it's a thousands separator
          if (stripped_value.match('#,\d{3}#')) {
            // it's a thousands separator -> just strip
            stripped_value = stripped_value.replace(',', '');
          }
          else {
            // it has to be interpreted as a decimal
            // first remove all other decimals
            stripped_value = stripped_value.replace('.', '');
            stripped_value = stripped_value.replace(',', '.');
          }
        }
        return parseFloat(stripped_value);
      },

      /**
       * Will calculate the date of the next collection of
       * a CiviSEPA RCUR mandate
       */
      nextCollectionDate: function (cycle_day, start_date, grace_end = null, creditor_id = 'default') {
        cycle_day = parseInt(cycle_day);
        if (cycle_day < 1 || cycle_day > 30) {
          CRM.alert('Illegal cycle day detected: ' + cycle_day);
          return 'Error';
        }

        // earliest contribution date is: max(now+notice, start_date, grace_end)

        // first: calculate the earliest possible collection date
        let notice = parseInt(CRM.contract.sepaTools.sepaCreditorParameters[creditor_id].notice);
        let grace = parseInt(CRM.contract.sepaTools.sepaCreditorParameters[creditor_id].grace);
        let earliest_date = new Date();
        // see https://stackoverflow.com/questions/6963311/add-days-to-a-date-object
        earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + (notice - grace) * 86400000));

        // then: take start date into account
        if (start_date) {
          start_date = new Date(start_date);
          if (start_date.getTime() > earliest_date.getTime()) {
            earliest_date = start_date;
          }
        }

        // then: take grace period into account
        if (grace_end) {
          grace_end = new Date(grace_end);
          if (grace_end.getTime() > earliest_date.getTime()) {
            earliest_date = grace_end;
          }
        }

        // now move to the next cycle day
        let safety_check = 65; // max two months
        while (earliest_date.getDate() != cycle_day && safety_check > 0) {
          // advance one day
          earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + 86400000));
          safety_check = safety_check - 1;
        }
        if (safety_check == 0) {
          console.log('Error, cannot cycle to day ' + cycle_day);
        }

        // format to YYYY-MM-DD. Don't use toISOString() (timezone mess-up)
        let month = earliest_date.getMonth() + 1;
        month = month.toString();
        if (month.length == 1) {
          month = '0' + month;
        }
        let day = earliest_date.getDate().toString();
        if (day.length == 1) {
          day = '0' + day;
        }

        // console.log(earliest_date.getFullYear() + '-' + month + '-' + day);
        return earliest_date.getFullYear() + '-' + month + '-' + day;
      },
    },
  };

  function getPaymentMode() {
    let $el = $('#payment_option');
    if (!$el.length) {
      $el = $('select[name=payment_option]');
    }
    let val = $el.length ? $el.val() : '';
    return (val || '').toString();
  }

  function setRequired($els, isRequired) {
    $els.each(function () {
      let $f = $(this);
      if (isRequired) {
        if ($f.data('wasRequired')) {
          $f.attr('required', 'required');
        }
        $f.removeData('wasRequired');
      }
      else {
        if ($f.is('[required]')) {
          $f.data('wasRequired', true);
        }
        $f.removeAttr('required').removeAttr('aria-required');
      }
    });
  }

  function toggleSection(selector, visible) {
    let $sec = $(selector);
    if (!$sec.length) {
      return;
    }
    let $fields = $sec.find('input, select, textarea');
    if (visible) {
      $sec.show(300);
      $fields.prop('disabled', false);
      setRequired($fields, true);
    } else {
      $sec.hide(300);
      setRequired($fields, false);
      $fields.prop('disabled', true);
    }
  }

  function clearAmountAndFrequency() {
    let $amount = $('[name=payment_amount]');
    let $freq = $('[name=payment_frequency]');
    $amount.val('').trigger('input').trigger('change');
    if ($freq.is('select')) {
      $freq.val('');
      if (!$freq.val()) {
        $freq.prop('selectedIndex', 0).trigger('change');
      }
    }
    else {
      $freq.val('').trigger('change');
    }
  }

  /**
   * Adjust the visibility of the various elements
   */
  function showHidePaymentElements() {
    let new_mode = getPaymentMode();

    toggleSection('div.payment-select', false);
    toggleSection('div.payment-create', false);
    toggleSection('div.payment-sepa',   false);
    toggleSection('div.payment-modify', false);
    toggleSection('div.payment-show',   false);

    switch (new_mode) {
      case 'select':
        toggleSection('div.payment-select', true);
        break;

      case 'modify':
        toggleSection('div.payment-modify', true);
        break;

      case 'nochange':
      case 'NoChange':
        break;

      case 'None':
        clearAmountAndFrequency();
        break;

      case 'RCUR':
        toggleSection('div.payment-create', true);
        toggleSection('div.payment-sepa',   true);
        break;

      default:
        toggleSection('div.payment-create', true);
        break;
    }
    toggleSection('div.payment-schedule', true);
  }

  /**
   * Render some html code representing the contract (new or adjusted)
   */
  function renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode) {
    let iban_account_line = '';
    if (typeof iban === 'string' && iban.length > 0) {
      iban_account_line = ts('Member account', []) + ': ' + iban + '<br/>';
    }
    return '<div>' +
      ts('Paid by', []) + ': ' + debitor_name + '<br/>' +
      iban_account_line + ts('Creditor name', []) + ': ' + creditor.name + '<br/>' +
      ts('Creditor account', []) + ': ' + creditor.iban + '<br/>' +
      ts('Frequency', []) + ': ' + frequency_label + '<br/>' +
      ts('Payment method', []) + ': ' + mode + '<br/>' +
      ts('Annual amount', []) + ': ' + annual + '<br/>' +
      ts('Installment amount', []) + ': ' + (isNaN(installment) ? '0.00' : installment.toFixed(2)) + '<br/>' +
      ts('Next collection', []) + ': ' + next_collection + '<br/>' +
      '</div>';
  }

  /**
   * Update/render the payment summary (preview)
   */
  function updatePaymentSummaryText() {
    let mode = getPaymentMode();
    if (!mode) {
      return true;
    }
    else if (mode === 'select') {
      // display the selected recurring contribution
      let recurring_contributions = CRM.vars.contract.recurring_contributions;
      let key = $('[name=recurring_contribution]').val();
      if (key) {
        $('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
      }
      else {
        $('.recurring-contribution-summary-text').html(ts('None'));
      }

    }
    else if (mode === 'None') {
      $('#payment_amount').attr('placeholder', 0);
      $('.recurring-contribution-summary-text').html(ts('No payment required'));
      // NO CHANGE TO CONTRACT
    }
    else if (mode === 'nochange') {
      let current = CRM.vars.contract && CRM.vars.contract.current_contract || null;
      if (current && current.text_summary) {
        $('.recurring-contribution-summary-text').html(current.text_summary);
      }
      else {
        let list = CRM.vars.contract && CRM.vars.contract.recurring_contributions || {};
        let key  = CRM.vars.contract && CRM.vars.contract.current_recurring || null;
        if (key && list[key] && list[key].text_summary) {
          $('.recurring-contribution-summary-text').html(list[key].text_summary);
        }
        else {
          $('.recurring-contribution-summary-text').html(ts('None'));
        }
      }
    }
    else if (mode === 'modify') {
      // render the current SEPA values
      let current_values = CRM.vars.contract.current_contract;
      let creditor = CRM.vars.contract.creditor;
      let debitor_name = CRM.vars.contract.debitor_name;
      let cycle_day = $('[name=cycle_day]').val();
      let iban = $('[name=iban]').val();
      let installment = CRM.contract.sepaTools.parseMoney($('[name=payment_amount]').val());
      let frequency = $('[name=payment_frequency]').val();
      let frequency_label = CRM.vars.contract.frequencies[frequency];
      // let next_collection = CRM.vars.contract.next_collections[cycle_day];
      let start_date = $('[name=activity_date]').val();
      let annual = (installment.toFixed(2) * parseFloat(frequency)).toFixed(2);

      // In case of an update (not revive), we need to respect the already paid period, see #771
      let next_collection = '';
      if (CRM.vars.contract.action === 'update') {
        let defer_payment_start = parseInt($('[name=defer_payment_start]').val());
        if (defer_payment_start > 0) {
          // user has chosen to maintain cycle day
          next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date, CRM.vars.contract.grace_end);
        }
        else {
          next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date);
        }
      }
      else {
        next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date);
      }

      // add placeholders for IBAN,BIC,AMOUNT
      $('#iban').attr('placeholder', current_values.fields.iban);
      $('#bic').attr('placeholder', current_values.fields.bic);
      $('#payment_amount').attr('placeholder', current_values.fields.amount);

      if (!iban || !iban.length) {
        iban = current_values.fields.iban;
      }
      if (!installment) {
        installment = CRM.contract.sepaTools.parseMoney(current_values.fields.amount);
      }

      // calculate the installment
      if (!isNaN(installment)) {
        annual = (installment.toFixed(2) * parseFloat(frequency)).toFixed(2);
      }

      $('.recurring-contribution-summary-text').show().html(renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode));
      $('#payment_amount_currency').text(creditor.currency);

    }
    else {
      let creditor = CRM.vars.contract.creditor;
      let cycle_day = $('[name=cycle_day]').val();
      let iban = $('[name=iban]').val();
      let debitor_name = CRM.vars.contract.debitor_name;
      let installment = CRM.contract.sepaTools.parseMoney($('[name=payment_amount]').val());
      let frequency = $('[name=payment_frequency]').val();
      let frequency_label = CRM.vars.contract.frequencies[frequency];
      let start_date = $('[name=activity_date]').val();
      let next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date);
      let annual = (installment.toFixed(2) * parseFloat(frequency)).toFixed(2);

      $('.recurring-contribution-summary-text')
        .html(renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode))
        .show();
    }
  }

  // trigger the JS updates
  $(document).ready(function () {
    let _busy = false;
    function updatePreview(){
      if (_busy) {
        return;
      }
      _busy = true;
      updatePaymentSummaryText();
      _busy = false;
    }
    function updateAll(){
      if (_busy) {
        return;
      }
      _busy = true;
      showHidePaymentElements();
      updatePaymentSummaryText();
      _busy = false;
    }

    let $paymentOption = $('#payment_option');
    if (!$paymentOption.length) {
      $paymentOption = $('select[name=payment_option]');
    }

    $paymentOption
      .off('change.contract select2:select.contract select2:clear.contract')
      .on('change.contract select2:select.contract select2:clear.contract', updateAll);

    $('[name=recurring_contribution]')
      .off('change.contract select2:select.contract select2:clear.contract')
      .on('change.contract select2:select.contract select2:clear.contract', updatePreview);

    $('div.payment-modify :input, div.defer_payment_start :input,' +
      '#activity_date,#iban,#bic,#cycle_day,#payment_amount,#payment_frequency')
      .off('change.contract')
      .on('change.contract', updatePreview);

    updateAll();
  });

})(CRM.$ || cj, CRM.ts('de.systopia.contract'));
