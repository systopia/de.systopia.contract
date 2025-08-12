/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2025 SYSTOPIA                             |
| Author: SYSTOPIA (info@systopia.de)                          |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

(function ($, ts) {
  'use strict';

  function getPaymentMode() {
    var $el = $('#payment_option');
    if (!$el.length) $el = $('select[name=payment_option]');
    var val = $el.length ? $el.val() : '';
    return (val || '').toString();
  }

  function setRequired($els, isRequired) {
    $els.each(function () {
      var $f = $(this);
      if (isRequired) {
        if ($f.data('wasRequired')) $f.attr('required', 'required');
        $f.removeData('wasRequired');
      } else {
        if ($f.is('[required]')) $f.data('wasRequired', true);
        $f.removeAttr('required').removeAttr('aria-required');
      }
    });
  }

  function toggleSection(selector, visible) {
    var $sec = $(selector);
    if (!$sec.length) return;
    var $fields = $sec.find('input, select, textarea');
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
    var $amount = $('[name=payment_amount]');
    var $freq = $('[name=payment_frequency]');
    $amount.val('').trigger('input').trigger('change');
    if ($freq.is('select')) {
      $freq.val('');
      if (!$freq.val()) { $freq.prop('selectedIndex', 0).trigger('change'); }
    } else {
      $freq.val('').trigger('change');
    }
  }

  /**
   * Adjust the visibility of the various elements
   */
  function showHidePaymentElements() {
    var new_mode = getPaymentMode();

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
      var current = (CRM.vars.contract && CRM.vars.contract.current_contract) || null;
      if (current && current.text_summary) {
        $('.recurring-contribution-summary-text').html(current.text_summary);
      } else {
        var list = (CRM.vars.contract && CRM.vars.contract.recurring_contributions) || {};
        var key  = (CRM.vars.contract && CRM.vars.contract.current_recurring) || null;
        if (key && list[key] && list[key].text_summary) {
          $('.recurring-contribution-summary-text').html(list[key].text_summary);
        } else {
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
    var _busy = false;
    function updatePreview(){
      if (_busy) return;
      _busy = true;
      updatePaymentSummaryText();
      _busy = false;
    }
    function updateAll(){
      if (_busy) return;
      _busy = true;
      showHidePaymentElements();
      updatePaymentSummaryText();
      _busy = false;
    }

    var $paymentOption = $('#payment_option');
    if (!$paymentOption.length) { $paymentOption = $('select[name=payment_option]'); }

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
