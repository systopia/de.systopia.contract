{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
| B. Endres (endres -at- systopia.de)                          |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}
{crmScope extensionKey='de.systopia.contract'}
<div class="crm-block crm-form-block">
  <!-- <h3>
  {if $historyAction eq 'cancel'}
    Please choose a reason for cancelling this contract and click on '{$historyAction|ucfirst}' below.
  {elseif $isUpdate}
    Please make the required changes to the contract and click on '{$historyAction|ucfirst}' below.
  {else}
    Please confirm that you want to {$historyAction} this contract by clicking on '{$historyAction|ucfirst}' below.
  {/if}
</h3> -->
  {if $modificationActivity eq 'update' OR $modificationActivity eq 'revive' }

    <div class="crm-section">
      <div class="label">{ts}Payment Preview{/ts}</div>
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

    <div class="crm-section payment-modify">
      <div class="label">{$form.cycle_day.label}</div>
      <div class="content">{$form.cycle_day.html}&nbsp;&nbsp;{if $current_cycle_day}{ts 1=$current_cycle_day}(currently: %1){/ts}{/if}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section payment-modify">
      <div class="label">{$form.iban.label}</div>
      <div class="content">{$form.iban.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section payment-modify">
      <div class="label">{$form.bic.label}</div>
      <div class="content">{$form.bic.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section payment-modify">
      <div class="label">{$form.payment_amount.label}</div>
      <div class="content">{$form.payment_amount.html}&nbsp;<span id="payment_amount_currency"></span></div>
      <div class="clear"></div>
    </div>
    <div class="crm-section payment-modify">
      <div class="label">{$form.payment_frequency.label}</div>
      <div class="content">{$form.payment_frequency.html}</div>
      <div class="clear"></div>
    </div>


    <div class="crm-section">
      <div class="label">{$form.membership_type_id.label}</div>
      <div class="content">{$form.membership_type_id.html}</div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{$form.campaign_id.label}</div>
      <div class="content">{$form.campaign_id.html}</div>
      <div class="clear"></div>
    </div>
  {/if}
  {if $form.cancel_date.html}
    <div class="crm-section">
      <div class="label">{$form.cancel_date.label}</div>
      <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=cancel_date}</div>
      <div class="clear"></div>
    </div>
  {/if}
  {if $form.resume_date.html}
    <div class="crm-section">
      <div class="label">{$form.resume_date.label}</div>
      <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=resume_date}</div>
      <div class="clear"></div>
    </div>
  {/if}
  {if $form.cancel_reason.html}
    <div class="crm-section">
      <div class="label">{$form.cancel_reason.label}</div>
      <div class="content">{$form.cancel_reason.html}</div>
      <div class="clear"></div>
    </div>
  {/if}
  <hr />
  <div class="crm-section">
    <div class="label">{$form.activity_date.label} {help id="scheduling" file="CRM/Contract/Form/Scheduling.hlp"}</div>
    <div class="content">{include file="CRM/common/jcalendar.tpl" elementName=activity_date}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.activity_medium.label}</div>
    <div class="content">{$form.activity_medium.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.activity_details.label}</div>
    <div class="content">{$form.activity_details.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>

{if $modificationActivity eq 'update' OR $modificationActivity eq 'revive'}

{if $bic_lookup_accessible}
  {include file="CRM/Contract/Form/bic_lookup.tpl" location="bottom"}
{/if}

{/if}
{/crmScope}