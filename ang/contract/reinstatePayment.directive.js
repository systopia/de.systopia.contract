/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

'use strict';

(function(angular) {
  angular.module('contract').directive('contractReinstatePayment', function () {
    return {
      restrict: 'E',
      scope: {
        id: '=',
      },
      templateUrl: '~/contract/reinstatePayment.template.html',
      controller: ['$scope', '$element', 'crmApi4', 'crmStatus', 'contractCurrencyFilter', function ($scope, $element, crmApi4, crmStatus, contractCurrencyFilter) {
          $scope.ts = CRM.ts('contract');
          $scope.contractCurrencyFilter = contractCurrencyFilter;

          $scope.running = false;
          $scope.usesSepaMandate = null;
          $scope.isPaymentSuspended = null;
          crmApi4('Membership', 'get', {
            select: ['id', 'contact_id.display_name', 'status_id:name', 'membership_payment.membership_recurring_contribution', 'membership_payment.membership_frequency'],
            where: [['id', '=', $scope.id]],
          }).then(function (result) {
            $scope.membership = result[0];
            $scope.isPaymentSuspended = $scope.membership['status_id:name'] === 'PaymentSuspended';

            if (!$scope.membership['membership_payment.membership_recurring_contribution']) {
              $scope.usesSepaMandate = false;
              return;
            }

            $scope.paymentFrequency = $scope.membership['membership_payment.membership_frequency'];

            crmApi4('SepaMandate', 'get', {
              'select': ['id', 'iban', 'bic', 'account_holder', 'outstanding_amount'],
              'where': [
                ['entity_table', '=', 'civicrm_contribution_recur'],
                ['entity_id', '=', $scope.membership['membership_payment.membership_recurring_contribution']],
              ],
            }).then(function (result) {
              $scope.usesSepaMandate = Boolean(result[0]);
              if ($scope.usesSepaMandate) {
                $scope.outstandingAmount = result[0].outstanding_amount;
                $scope.iban = result[0].iban;
                $scope.bic = result[0].bic;
                $scope.accountHolder = result[0].account_holder;
              }
            });

            crmApi4('ContributionRecur', 'get', {
              'select': ['amount', 'frequency_interval', 'cycle_day', 'currency'],
              'where': [['id', '=', $scope.membership['membership_payment.membership_recurring_contribution']]]
            }).then(function (result) {
              if (!result[0]) {
                return;
              }

              $scope.paymentAmount = result[0].amount;
              $scope.currency = result[0].currency;
              $scope.cycleDay = result[0].cycle_day;
            });

            crmApi4('Membership', 'getFields', {
              loadOptions: true,
              where: [["name", "=", "membership_payment.membership_frequency"]],
              select: ["options"]
            }).then((fields) => {
              $scope.paymentFrequencies = {
                1: fields[0].options[1],
                2: fields[0].options[2],
                4:  fields[0].options[4],
                12:  fields[0].options[12],
              };
            });
          });

          $scope.close = function () {
            let dialog = $element.closest('.ui-dialog-content');
            if (dialog.length) {
              dialog.dialog('close');
            }
            else {
              $element.unblock();
            }
          }

          $scope.submit = function() {
            $scope.running = true;
            crmStatus(
              {},
              crmApi4('Contract', 'reinstatePayment', {
                id: $scope.membership.id,
                collectOutstanding: $scope.collectOutstanding,
                replaceSepaMandate: $scope.replaceSepaMandate,
                iban: $scope.iban,
                bic: $scope.bic,
                accountHolder: $scope.accountHolder,
                paymentAmount: $scope.paymentAmount,
                paymentFrequency: $scope.paymentFrequency,
                cycleDay: $scope.cycleDay,
                notes: $scope.notes,
              })
            ).then(function (result) {
              $element.trigger('crmFormSuccess', {
                submissionResponse: result,
              });

              $scope.close();

              $scope.isPaymentSuspended = false;
              $scope.running = false;
            }).catch(() => $scope.running = false);
          }
        }
      ],
    };
  });
})(angular);
