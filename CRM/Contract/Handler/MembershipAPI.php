<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         P. Figel (pfigel -at- greenpeace.org)                |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

/**
 * Amendments to the "regular" CiviCRM membership API
 */
class CRM_Contract_Handler_MembershipAPI implements API_Wrapper {

  /**
   * @inheritDoc
   */
  public function fromApiInput($apiRequest) {

    // TODO: anything else to check before execution?

    if ('create' === $apiRequest['action'] || 'edit' === $apiRequest['action']) {
      $params = $apiRequest['params'];

      // verify contract number
      CRM_Contract_CustomData::labelCustomFields($params);
      if (!empty($params['membership_general.membership_contract'])) {
        // validate contact number
        $validation_error = CRM_Contract_Validation_ContractNumber::verifyContractNumber(
          $params['membership_general.membership_contract'],
          $params['id'] ?? NULL
        );
        if ($validation_error) {
          throw new \RuntimeException($validation_error, 1);
        }
      }
    }

    return $apiRequest;
  }

  /**
   * @inheritDoc
   */
  public function toApiOutput($apiRequest, $result) {
    // TODO: anything to amend in the output
    return $result;
  }

}
