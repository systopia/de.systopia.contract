/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2022 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

// trigger the JS updates
cj(document).ready(function () {
    for (const activity_id of CRM.vars['de.systopia.contract']['ce_activity_types']) {
        cj("li.crm-activity-type_" + activity_id).hide();
    }
    console.log("Contract extension: activity actions for the following type IDs hidden: " + CRM.vars['de.systopia.contract']['ce_activity_types']);
});