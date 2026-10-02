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
    <th>{ts}Reason{/ts}</th>
    <th>{ts}Added by{/ts}</th>

    <th>{ts}Status{/ts}</th>
    <th>{ts}Actions{/ts}</th>

  </tr>

  {foreach from=$activities item=a}
    <tr class="{if $a.status_id_name eq 'Needs Review'}needs-review{/if} {if $a.status_id_name eq 'Scheduled'}scheduled{/if} {if $a.status_id_name eq 'Failed'}failed{/if}">
      <td title="{$a.display_hover_title}">{$a.display_title}</td>
      <td>{$a.activity_date_time|crmDate}</td>
      <td>
        {if $a.recurring_contribution_id}
          <a href="{crmURL p='civicrm/contact/view/contributionrecur' q="reset=1&id=`$a.recurring_contribution_id`&cid=`$a.recurring_contribution_contact_id`"}" class="crm-popup">{$a.payment_instrument_id_label}</a>
        {/if}
      </td>
      <td>{if $a.contract_updates_ch_annual || $a.contract_updates_ch_amount}{$a.contract_updates_ch_annual|crmMoney:$currency} ({$a.contract_updates_ch_amount|crmMoney:$currency}){/if}</td>

      <td>{$a.contract_updates_ch_frequency_label}</td>
      <td>{$a.contract_updates_ch_cycle_day}</td>
      <td>{$membershipTypes[$a.contract_updates_ch_membership_type]}</td>
      <td>{$a.campaign_id_title|truncate:50}</td>

      <td>{$a.medium_id_label}</td>
      <td>{$a.details|truncate:50}</td>
      <td>{$a.reason_label|truncate:50}</td>
      <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$a.source_contact_id`"}">{$contacts[$a.source_contact_id]}</a></td>

      <td>{$a.status_id_label}</td>
      <td>
        {if $a.status_id_name neq 'Completed'}
          <a class="edit-activity" href="{crmURL p='civicrm/activity/add' q="action=update&reset=1&id=`$a.id`&context=activity&searchContext=activity&cid=`$a.target_contact_id.0`"}">{ts}Edit{/ts}</a>
        {/if}
      </td>
    </tr>
  {/foreach}
</table>

<script>
  // hide selected columns
{foreach from=$hide_columns item=column_index}
  cj("table.contract-history-table").find('td:nth-child({$column_index}),th:nth-child({$column_index})').hide();
{/foreach}
</script>
{/crmScope}
