/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2022 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

// trigger the JS updates
cj(document).ready(function () {
    // register UI update functions
    cj("#payment_option").change(function () {
        updatePaymentSummaryText();
        showHidePaymentElements();
    }).change();

    // run the following once
    showHidePaymentElements();
    cj('[name=recurring_contribution]').change(updatePaymentSummaryText);
    cj("div.payment-modify").change(updatePaymentSummaryText);
    cj("div.defer_payment_start").change(updatePaymentSummaryText);
    cj("#activity_date").parent().parent().change(updatePaymentSummaryText);
});

/**
 * Adjust the visibility of the various elements
 */
function showHidePaymentElements() {
    let new_mode = cj("#payment_option").val();
    if (new_mode === "select") {
        cj("div.payment-select").show(300);
        cj("div.payment-modify").hide(300);
        cj("div.payment-create").hide(300);

    } else if (new_mode === "modify") {
        cj("div.payment-select").hide(300);
        cj("div.payment-modify").show(300);
        cj("div.payment-create").hide(300);

    } else if (new_mode === "create") {
      cj("div.payment-select").hide(300);
      cj("div.payment-modify").hide(300);
      cj("div.payment-create").show(300);

    } else {
        cj("div.payment-select").hide(300);
        cj("div.payment-modify").hide(300);
        cj("div.payment-show").hide(300);
    }
}

/**
 * Update/render the payment summary (preview)
 */
function updatePaymentSummaryText() { (function (cj, ts){
  console.log("updatePaymentSummaryText");
    let mode = cj("#payment_option").val();
    console.log(mode);
    if (mode === "select") {
        // display the selected recurring contribution
        let recurring_contributions = CRM.vars['de.systopia.contract'].recurring_contributions;
        let key = cj('[name=recurring_contribution]').val();
        if (key) {
            cj('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
        } else {
            cj('.recurring-contribution-summary-text').html(ts('None'));
        }


    } else if (mode === "nochange") {
        let recurring_contributions = CRM.vars['de.systopia.contract'].recurring_contributions;
        let key = CRM.vars['de.systopia.contract'].current_recurring;
        if (key in recurring_contributions) {
            cj('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
        } else {
            cj('.recurring-contribution-summary-text').html(ts('None'));
        }

    } else if (mode === "modify") {
        // render the current SEPA values
        let current_values = CRM.vars['de.systopia.contract'].current_contract;
        let creditor = CRM.vars['de.systopia.contract'].creditor;
        let debitor_name = CRM.vars['de.systopia.contract'].debitor_name;
        let cycle_day = cj('[name=cycle_day]').val();
        let iban = cj('[name=iban]').val();
        let installment = parseMoney(cj('[name=payment_amount]').val());
        let frequency = cj('[name=payment_frequency]').val();
        let frequency_label = CRM.vars['de.systopia.contract'].frequencies[frequency];
        // let next_collection = CRM.vars['de.systopia.contract'].next_collections[cycle_day];
        let start_date = cj('[name=activity_date]').val();
        let annual = 0.0;

        // In case of an update (not revive), we need to respect the already paid period, see #771
        let next_collection = '';
        if (CRM.vars['de.systopia.contract'].action === 'update') {
            let defer_payment_start = parseInt(cj('[name=defer_payment_start]').val());
            if (defer_payment_start > 0) {
                // user has chosen to maintain cycle day
                //console.log("GRACE END" + CRM.vars['de.systopia.contract'].grace_end);
                next_collection = nextCollectionDate(cycle_day, start_date, CRM.vars['de.systopia.contract'].grace_end);
            } else {
                next_collection = nextCollectionDate(cycle_day, start_date, null);
            }
        } else {
            next_collection = nextCollectionDate(cycle_day, start_date, null);
        }
        //console.log(next_collection);

        // add placeholders for IBAN,BIC,AMOUNT
        cj("#iban").attr("placeholder", current_values.fields.iban);
        cj("#bic").attr("placeholder", current_values.fields.bic);
        cj("#payment_amount").attr("placeholder", current_values.fields.amount);

        // fill with old fields
        if (!iban.length) {
            iban = current_values.fields.iban;
        }
        if (!installment) {
            installment = parseMoney(current_values.fields.amount);
        }

        // calculate the installment
        if (!isNaN(installment)) {
            annual = (installment.toFixed(2) * parseFloat(frequency)).toFixed(2);
        }

        // TODO: use template?
        cj('.recurring-contribution-summary-text').html(
            ts("Debitor name") + ": " + debitor_name + "<br/>" +
            ts("Debitor account") + ": " + iban + "<br/>" +
            ts("Creditor name") + ": " + creditor.name + "<br/>" +
            ts("Creditor account") + ": " + creditor.iban + "<br/>" +
            ts("Payment method: SEPA Direct Debit") + "<br/>" +
            ts("Frequency") + ": " + frequency_label + "<br/>" +
            ts("Annual amount") + ": " + annual + " " + creditor.currency + "<br/>" +
            ts("Installment amount") + ": " + installment.toFixed(2) + " " + creditor.currency + "<br/>" +
            ts("Next debit") + ": " + next_collection + "<br/>"
        );

        cj('#payment_amount_currency').text(creditor.currency);
      }
    }(CRM.$, CRM.ts('de.systopia.contract')));
}

