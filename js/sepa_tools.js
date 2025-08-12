/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2021-2025 SYSTOPIA                             |
| Author: SYSTOPIA (info@systopia.de)                          |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

(function ($, ts) {
  'use strict';
  CRM.contract = {
    sepaTools: {
      sepaCreditorParameters: CRM.vars.contract.sepa_creditor_parameters,

      /**
       * formats a value to the CiviCRM failsafe format: 0.00 (e.g. 999999.90)
       * even if there are ',' in there, which are used in some countries
       * (e.g. Germany, Austria,) as a decimal point.
       * @see CRM_Contract_SepaLogic::formatMoney
       */
      parseMoney: function (raw_value) {
        if (raw_value.length == 0) {
          return 0.0;
        }

        // find out if there's a problem with ','
        let stripped_value = raw_value.replace(' ', '');
        if (stripped_value.includes(',')) {
          // if there are at least three digits after the ','
          //  it's a thousands separator
          if (stripped_value.match('#,\d{3}#')) {
            // it's a thousands separator -> just strip
            stripped_value = stripped_value.replace(',', '');
          }
          else {
            // it has to be interpreted as a decimal
            // first remove all other decimals
            stripped_value = stripped_value.replace('.', '');
            stripped_value = stripped_value.replace(',', '.');
          }
        }
        return parseFloat(stripped_value);
      },

      /**
       * Will calculate the date of the next collection of
       * a CiviSEPA RCUR mandate
       */
      nextCollectionDate: function (cycle_day, start_date, grace_end = null, creditor_id = 'default') {
        cycle_day = parseInt(cycle_day);
        if (cycle_day < 1 || cycle_day > 30) {
          CRM.alert('Illegal cycle day detected: ' + cycle_day);
          return 'Error';
        }

        // earliest contribution date is: max(now+notice, start_date, grace_end)

        // first: calculate the earliest possible collection date
        let notice = parseInt(CRM.contract.sepaTools.sepaCreditorParameters[creditor_id]['notice']);
        let grace = parseInt(CRM.contract.sepaTools.sepaCreditorParameters[creditor_id]['grace']);
        let earliest_date = new Date();
        // see https://stackoverflow.com/questions/6963311/add-days-to-a-date-object
        earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + (notice - grace) * 86400000));

        // then: take start date into account
        if (start_date) {
          start_date = new Date(start_date);
          if (start_date.getTime() > earliest_date.getTime()) {
            earliest_date = start_date;
          }
        }

        // then: take grace period into account
        if (grace_end) {
          grace_end = new Date(grace_end);
          if (grace_end.getTime() > earliest_date.getTime()) {
            earliest_date = grace_end;
          }
        }

        // now move to the next cycle day
        let safety_check = 65; // max two months
        while (earliest_date.getDate() != cycle_day && safety_check > 0) {
          // advance one day
          earliest_date = new Date(earliest_date.setTime(earliest_date.getTime() + 86400000));
          safety_check = safety_check - 1;
        }
        if (safety_check == 0) {
          console.log('Error, cannot cycle to day ' + cycle_day);
        }

        // format to YYYY-MM-DD. Don't use toISOString() (timezone mess-up)
        let month = earliest_date.getMonth() + 1;
        month = month.toString();
        if (month.length == 1) {
          month = '0' + month;
        }
        let day = earliest_date.getDate().toString();
        if (day.length == 1) {
          day = '0' + day;
        }

        // console.log(earliest_date.getFullYear() + '-' + month + '-' + day);
        return earliest_date.getFullYear() + '-' + month + '-' + day;
      },
    },
  };
})(CRM.$ || cj, CRM.ts('de.systopia.contract'));
