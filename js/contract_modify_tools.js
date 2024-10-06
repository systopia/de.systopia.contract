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
    cj("#iban").parent().parent().change(updatePaymentSummaryText);
    cj("#bic").parent().parent().change(updatePaymentSummaryText);
    cj("#cycle_day").parent().parent().change(updatePaymentSummaryText);
    cj("#payment_amount").parent().parent().change(updatePaymentSummaryText);
    cj("#payment_frequency").parent().parent().change(updatePaymentSummaryText);
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

    } else if (new_mode === "nochange") {
      cj("div.payment-select").hide(300);
      cj("div.payment-modify").hide(300);
      cj("div.payment-create").hide(300);

    } else { // new contract
        cj("div.payment-create").show(300);
        cj("div.payment-select").hide(300);
        cj("div.payment-modify").hide(300);
        cj("div.payment-show").hide(300);
        if (new_mode === "Cash") {
            cj("#iban").parent().parent().hide(300);
            cj("#bic").parent().parent().hide(300);
        } else {
            cj("#iban").parent().parent().show(300);
            cj("#bic").parent().parent().show(300);
        }
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
    if (typeof(iban) === 'string' && iban.length > 0) {
        iban_account_line =  ts("Member account", []) + ": " + iban + "<br/>";
    }

    return "<div>" +
            ts("Paid by", []) + ": \t" + debitor_name + "<br/>" +
            iban_account_line +
            ts("Creditor name", []) + ": " + creditor.name + "<br/>" +
            ts("Creditor account", []) + ": " + creditor.iban + "<br/>" +
            ts("Frequency", []) + ": " + frequency_label + "<br/>" +
            ts("Payment method", []) + ": " + mode + "<br/>" +
            ts("Annual amount", []) + ": " + annual + "<br/>" +
            ts("Installment amount", []) + ": " + installment.toFixed(2) + "<br/>" +
            ts("Next collection", []) + ": " + next_collection + "<br/>" +
        "</div>";

//     // TODO: use template?
//     cj('.recurring-contribution-summary-text').html(
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
//     cj('#payment_amount_currency').text(creditor.currency);
// }
}


/**
 * Update/render the payment summary (preview)
 */
function updatePaymentSummaryText() { (function (cj, ts){
    let mode = cj("#payment_option").val();
    console.log("updatePaymentSummaryText: " + mode);

    // SELECT EXISTING CONTRACT
    if (mode === "select") {
        // display the selected recurring contribution
        let recurring_contributions = CRM.vars['de.systopia.contract'].recurring_contributions;
        let key = cj('[name=recurring_contribution]').val();
        if (key) {
            cj('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
        } else {
            cj('.recurring-contribution-summary-text').html(ts('None'));
        }

    // NO CHANGE TO CONTRACT
    } else if (mode === "nochange") {
        let recurring_contributions = CRM.vars['de.systopia.contract'].recurring_contributions;
        let key = CRM.vars['de.systopia.contract'].current_recurring;
        if (key in recurring_contributions) {
            cj('.recurring-contribution-summary-text').html(recurring_contributions[key].text_summary);
        } else {
            cj('.recurring-contribution-summary-text').html(ts('None'));
        }

    // ADJUST EXISTING MANDATE
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

        cj('.recurring-contribution-summary-text').show().html(
            renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode));
        cj('#payment_amount_currency').text(creditor.currency);


    } else {  // CREATE NEW CONTRACT
        let creditor_id = cj('[name=creditor]').val();
        let creditor = CRM.vars['de.systopia.contract'].creditor;
        let cycle_day = cj('[name=cycle_day]').val();
        let iban = cj('[name=iban]').val();
        let debitor_name = CRM.vars['de.systopia.contract'].debitor_name;
        let installment = parseMoney(cj('[name=payment_amount]').val());
        let frequency = cj('[name=payment_frequency]').val();
        let frequency_label = CRM.vars['de.systopia.contract'].frequencies[frequency];
        let start_date = cj('[name=activity_date]').val();
        let next_collection = nextCollectionDate(cycle_day, start_date, null);
        let annual = 0.0;

        cj('.recurring-contribution-summary-text')
            .html(renderContractPreview(debitor_name, iban, creditor, frequency_label, annual, installment, next_collection, mode))
            .show();
    }
    }(CRM.$, CRM.ts('de.systopia.contract')));

}

