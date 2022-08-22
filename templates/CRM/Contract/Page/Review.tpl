{*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+-------------------------------------------------------------*}
{crmScope extensionKey='de.systopia.contract'}
<table class="contract-history-table">
  <tr>

    <th>{ts}Modification{/ts}</th>
    <th>{ts}Date{/ts}</th>
    <th>{ts}Payment method{/ts}</th>
    <th>{ts}Amount{/ts}</th>

    <th>{ts}Frequency{/ts}</th>
    <th>{ts}Cycle day{/ts}</th>
    <th>{ts}Type{/ts}</th>
    <th>{ts}Campaign{/ts}</th>

    <th>{ts}Medium{/ts}</th>
    <th>{ts}Note{/ts}</th>
    <th>{ts}Cancel reason{/ts}</th>
    <th>{ts}Added by{/ts}</th>

    <th>{ts}Status{/ts}</th>
    <th>{ts}Edit{/ts}</th>

  </tr>

  {foreach from=$activities item=a}
    <tr class="{if $activityStatuses[$a.status_id] eq 'Needs Review'}needs-review{/if} {if $activityStatuses[$a.status_id] eq 'Scheduled'}scheduled{/if}">

      <td>{$a.id} {$activityTypes[$a.activity_type_id]}</td>
      <td>{$a.activity_date_time|crmDate}</td>
      <td><a href="{crmURL p='civicrm/contact/view/contributionrecur' q="reset=1&id=`$a.contract_updates_ch_recurring_contribution`&cid=`$a.recurring_contribution_contact_id`"}" class="crm-popup">{$paymentInstruments[$a.payment_instrument_id]}</a></td>
      <td>{if $a.contract_updates_ch_annual || $a.contract_updates_ch_amount}{$a.contract_updates_ch_annual|crmMoney:$currency} ({$a.contract_updates_ch_amount|crmMoney:$currency}){/if}</td>

      <td>{$paymentFrequencies[$a.contract_updates_ch_frequency]}</td>
      <td>{$a.contract_updates_ch_cycle_day}</td>
      <td>{$membershipTypes[$a.contract_updates_ch_membership_type]}</td>
      <td>{$campaigns[$a.campaign_id]|truncate:50}</td>

      <td>{$mediums[$a.medium_id]}</td>
      <td>{$a.details|truncate:50}</td>
      <td>{$cancelReasons[$a.contract_cancellation_contact_history_cancel_reason]|truncate:50}</td>
      <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$a.source_contact_id`"}">{$contacts[$a.source_contact_id]}</a></td>

      <td>{$activityStatuses[$a.status_id]}</td>
      <td>{if $activityStatuses[$a.status_id] neq 'Completed'} <a class="edit-activity" href="{crmURL p='civicrm/activity/add' q="action=update&reset=1&id=`$a.id`&context=activity&searchContext=activity&cid=`$a.target_contact_id.0`"}" class="create-mandate">edit</a> {/if}</td>
    </tr>
  {/foreach}
</table>
{/crmScope}