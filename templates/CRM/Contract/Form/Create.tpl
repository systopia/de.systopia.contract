{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2024 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}

{crmScope extensionKey='de.systopia.contract'}

  <h3>{ts}Zugang{/ts}</h3>
  <div class="crm-section">
    <div class="label">{$form.membership_dialoger.label}</div>
    <div class="content">{$form.membership_dialoger.html}</div>
    <div class="clear"></div>
  </div>


  <h3>{ts}Mitgliedschaftsdaten{/ts}</h3>

  <div class="crm-section">
    <div class="label">{$form.membership_type_id.label}</div>
    <div class="content">{$form.membership_type_id.html}</div>
    <div class="clear"></div>
  </div>

{*  <div class="crm-section">*}
{*    <div class="label">{$form.membership_association.label}</div>*}
{*    <div class="content">{$form.membership_association.html}</div>*}
{*    <div class="clear"></div>*}
{*  </div>*}

  <div class="crm-section">
    <div class="label">{$form.membership_contract.label}</div>
    <div class="content">{$form.membership_contract.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.join_date.label}</div>
    <div class="content">{$form.join_date.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{ts}Startdatum{/ts}</div>
    <div class="content">{$form.start_date.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.end_date.label}</div>
    <div class="content">{$form.end_date.html}</div>
    <div class="clear"></div>
  </div>


  <h3>{ts}Zahlungsvertrag{/ts}</h3>

  <div class="crm-section">
    <div class="label">{ts}Contract Preview{/ts}</div>
    <div class="content recurring-contribution-summary-text">{ts}None{/ts}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.payment_option.label}</div>
    <div class="content">{$form.payment_option.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section payment-select">
    <div class="label">{$form.recurring_contribution.label}</div>
    <div class="content">{$form.recurring_contribution.html}</div>
    <div class="clear"></div>
    <div class="label"></div>
    <div class="clear"></div>
  </div>

  <div class="crm-section payment-create">
    <div class="label">{$form.cycle_day.label}</div>
    <div class="content">{$form.cycle_day.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.iban.label}</div>
    <div class="content">{$form.iban.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.bic.label}</div>
    <div class="content">{$form.bic.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.account_holder.label}</div>
    <div class="content">{$form.account_holder.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_amount.label}</div>
    <div class="content">{$form.payment_amount.html}&nbsp;<span id="payment_amount_currency"></span></div>
    <div class="clear"></div>
  </div>
  <div class="crm-section payment-create">
    <div class="label">{$form.payment_frequency.label}</div>
    <div class="content">{$form.payment_frequency.html}</div>
    <div class="clear"></div>
  </div>

  <h3 class="id-notes" onClick="cj('div.membership-create-notes').show();">Notiz hinzufügen (KLICK)</h3>

  <div class="crm-section membership-create-notes" style="display:none;" >
    <div class="label">{$form.activity_details.label}</div>
    <div class="content">{$form.activity_details.html}</div>
    <div class="clear"></div>
  </div>
  <hr />

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>



{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}

  <script>
    // hide the notes until clicked
    cj("h3.id-notes").show();

  </script>
{/crmScope}
