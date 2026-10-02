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

(function(angular, $) {
  angular.module('contract').directive('contractSuspendPayment', function () {
    return {
      restrict: 'E',
      scope: {
        id: '=',
      },
      templateUrl: '~/contract/suspendPayment.template.html',
      controller: ['$scope', '$element', 'crmApi4', 'crmStatus', function ($scope, $element, crmApi4, crmStatus) {
          const ts = $scope.ts = CRM.ts('contract');

          $scope.running = false;
          $scope.usesSepaMandate = null;
          $scope.isCurrent = null;
          crmApi4('Membership', 'get', {
            select: ['id', 'contact_id.display_name', 'status_id:name', 'membership_payment.membership_recurring_contribution'],
            where: [['id', '=', $scope.id]],
          }).then(function (result) {
            $scope.membership = result[0];
            $scope.isCurrent = $scope.membership['status_id:name'] === 'Current';

            if (!$scope.membership['membership_payment.membership_recurring_contribution']) {
              $scope.usesSepaMandate = false;
              return;
            }

            crmApi4('SepaMandate', 'get', {
              'select': ['id'],
              'where': [
                ['entity_table', '=', 'civicrm_contribution_recur'],
                ['entity_id', '=', $scope.membership['membership_payment.membership_recurring_contribution']],
              ],
            }).then(function (result) {
              $scope.usesSepaMandate = Boolean(result[0]);
            })
          });

          crmApi4('Activity', 'getFields', {
            loadOptions: true,
            where: [['name', '=', 'contract_payment_suspended.reason']],
            select: ['options']
          }).then((result) => $scope.reasons = result[0].options);

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
              crmApi4('Contract', 'suspendPayment', {
                id: $scope.membership.id,
                reason: $scope.reason,
                notes: $scope.notes,
              })
            ).then(function (result) {
              $element.trigger('crmFormSuccess', {
                submissionResponse: result,
              });

              $scope.close();

              $scope.isCurrent = false;
              $scope.running = false;
            });
          }
        }
      ],
    };
  });
})(angular, CRM.$);
