/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2025 SYSTOPIA                             |
| Author: SYSTOPIA (info@systopia.de)                          |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

(function ($, ts) {
  'use strict';

  /**
   * Adjust the visibility of the various elements
   */
  function showHidePaymentElements() {
    let new_mode = $('#payment_option').val();
    if (new_mode === 'select') {
      $('div.payment-modify').hide(300);
      $('div.payment-create').hide(300);
      $('div.payment-sepa').hide(300);
      $('div.payment-select').show(300);

    }
    else if (new_mode === 'none') {
      $('div.payment-select').hide(300);
      $('div.payment-create').hide(300);
      $('div.payment-sepa').hide(300);
      $('div.payment-modify').hide(300);

    }
    else if (new_mode === 'modify') {
      $('div.payment-select').hide(300);
      $('div.payment-create').hide(300);
      $('div.payment-sepa').hide(300);
      $('div.payment-modify').show(300);

    }
    else if (new_mode === 'RCUR') {
      $('div.payment-select').hide(300);
      $('div.payment-modify').hide(300);
      $('div.payment-create').show(300);
      $('div.payment-sepa').show(300);

    }
    else if (new_mode === 'nochange') {
      $('div.payment-select').hide(300);
      $('div.payment-modify').hide(300);
      $('div.payment-create').hide(300);
      $('div.payment-sepa').hide(300);

    }
    else {
      // new contract
      $('div.payment-select').hide(300);
      $('div.payment-modify').show(300);
      $('div.payment-show').hide(300);
      $('div.payment-create').show(300);
      $('div.payment-sepa').hide(300);
    }
  }

  /**
   * Render some html code representing the contract (new or adjusted)
   */
  function renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode) {
    // let rows = {};
    // rows[ts("Member name", [])] = debitor_name;
    // rows[ts("Member account", [])] = iban;
    // rows[ts("Creditor name", [])] = creditor.name;
    // rows[ts("Creditor account", [])] = creditor.iban;
    // rows[ts("Payment method", [])] = mode;
    // rows[ts("Frequency", [])] = debitor_name;
    // rows[ts("Annual amount", [])] = debitor_name;
    // rows[ts("Installment amount", [])] = installment.toFixed(2);
    // rows[ts("Next collection", [])] = next_collection;

    // this won't get small enough:
    // return "<table style='border: 0;'>" +
    //     "<tr><td>" + ts("Member name", []) + "</td><td'>" + debitor_name + "</td></tr>" +
    //     "<tr><td>" + ts("Member account", []) + "</td><td>" + iban + "</td></tr>" +
    //     "<tr><td>" + ts("Creditor name", []) + "</td><td>" + creditor.name + "</td></tr>" +
    //     "<tr><td>" + ts("Creditor account", []) + "</td><td>" + creditor.iban + "</td></tr>" +
    //     "<tr><td>" + ts("Payment method", []) + "</td><td>" + creditor.iban + "</td></tr>" +
    //     "<tr><td>" + ts("Frequency", []) + "</td><td>" + frequency_label + "</td></tr>" +
    //     "<tr><td>" + ts("Payment method", []) + "</td><td>" + mode + "</td></tr>" +
    //     "<tr><td>" + ts("Annual amount", []) + "</td><td>" + annual + "</td></tr>" +
    //     "<tr><td>" + ts("Installment amount", []) + "</td><td>" + installment.toFixed(2) + "</td></tr>" +
    //     "<tr><td>" + ts("Next collection", []) + "</td><td>" + next_collection + "</td></tr>" +
    //  "</table>";

    // this won't get small enough:
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
      ts('Installment amount', []) + ': ' + installment.toFixed(2) + '<br/>' +
      ts('Next collection', []) + ': ' + next_collection + '<br/>' +
      '</div>';

    //     // TODO: use template?
    //     $('.recurring-contribution-summary-text').html(
    //         ts("Debitor name") + ": " + debitor_name + "<br/>" +
    //         ts("Debitor account") + ": " + iban + "<br/>" +
    //         ts("Creditor name") + ": " + creditor.name + "<br/>" +
    //         ts("Creditor account") + ": " + creditor.iban + "<br/>" +
    //         ts("Payment method: SEPA Direct Debit") + "<br/>" +
    //         ts("Frequency") + ": " + frequency_label + "<br/>" +
    //         ts("Annual amount") + ": " + annual + " " + creditor.currency + "<br/>" +
    //         ts("Installment amount") + ": " + installment.toFixed(2) + " " + creditor.currency + "<br/>" +
    //         ts("Next debit") + ": " + next_collection + "<br/>"
    //     );
    //     $('#payment_amount_currency').text(creditor.currency);
    // }
  }

  /**
   * Update/render the payment summary (preview)
   */
  function updatePaymentSummaryText() {
    let mode = $('#payment_option').val();
    if (mode === undefined) {
      // No payment option in the form (Cancel or Pause)
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
    else if (mode === 'none') {
      $('#payment_amount').attr('placeholder', 0);
      $('.recurring-contribution-summary-text').html(ts('No payment required'));
      // NO CHANGE TO CONTRACT
    }
    else if (mode === 'nochange') {
      let recurring_contributions = CRM.vars.contract.recurring_contributions;
      let key = CRM.vars.contract.current_recurring;
      if (key in recurring_contributions) {
        $('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
      }
      else {
        $('.recurring-contribution-summary-text').html(ts('None'));
      }

      // ADJUST EXISTING MANDATE
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
          //console.log("GRACE END" + CRM.vars.contract.grace_end);
          next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date, CRM.vars.contract.grace_end);
        }
        else {
          next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date);
        }
      }
      else {
        next_collection = CRM.contract.sepaTools.nextCollectionDate(cycle_day, start_date);
      }
      //console.log(next_collection);

      // add placeholders for IBAN,BIC,AMOUNT
      $('#iban').attr('placeholder', current_values.fields.iban);
      $('#bic').attr('placeholder', current_values.fields.bic);
      $('#payment_amount').attr('placeholder', current_values.fields.amount);

      // fill with old fields
      if (!iban.length) {
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
    else {  // CREATE NEW CONTRACT
      let creditor_id = $('[name=creditor]').val();
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
    // register UI update functions
    $('#payment_option').change(function () {
      updatePaymentSummaryText();
      showHidePaymentElements();
    }).change();

    // run the following once
    showHidePaymentElements();
    $('[name=recurring_contribution]').change(updatePaymentSummaryText);
    $('div.payment-modify').change(updatePaymentSummaryText);
    $('div.defer_payment_start').change(updatePaymentSummaryText);
    $('#activity_date').parent().parent().change(updatePaymentSummaryText);
    $('#iban').parent().parent().change(updatePaymentSummaryText);
    $('#bic').parent().parent().change(updatePaymentSummaryText);
    $('#cycle_day').parent().parent().change(updatePaymentSummaryText);
    $('#payment_amount').parent().parent().change(updatePaymentSummaryText);
    $('#payment_frequency').parent().parent().change(updatePaymentSummaryText);
  });

})(CRM.$ || cj, CRM.ts('de.systopia.contract'));
