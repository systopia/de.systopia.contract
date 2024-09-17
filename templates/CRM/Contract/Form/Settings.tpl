{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2023 SYSTOPIA                             |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

<div class="crm-block crm-form-block">
    <div class="help">{ts}Configuration Options for the Contract Extension{/ts}</div>

    <div class="crm-section payment-modify">
      <div class="label">{$form.contract_modification_reviewers.label} {help id="id-date-reviewers" title=$form.contract_modification_reviewers.label}</div>
      <div class="content">{$form.contract_modification_reviewers.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section payment-modify">
      <div class="label">{$form.date_adjustment.label}&nbsp;{help id="id-date-adjustment" title=$form.date_adjustment.label}</div>
      <div class="content">{$form.date_adjustment.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-section payment-modify">
      <div class="label">{$form.contract_payment_types.label}&nbsp;{help id="id-contract-payment-types" title=$form.contract_payment_types.label}</div>
      <div class="content">{$form.contract_payment_types.html}</div>
      <div class="clear"></div>
    </div>

    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

</div>
