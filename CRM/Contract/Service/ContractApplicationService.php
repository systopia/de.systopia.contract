<?php

declare(strict_types = 1);

class CRM_Contract_Service_ContractApplicationService {
  public function create(array $data): array {
    $tx = new CRM_Core_Transaction();
    try {
      $contactId = (int) ($data['contact_id'] ?? 0);
      $mode = $data['payment_option'] ?? '';
      if (empty($data['cycle_day']) || $data['cycle_day'] < 1 || $data['cycle_day'] > 30) {
        $data['cycle_day'] = CRM_Contract_SepaLogic::nextCycleDay();
      }
      if (empty($data['bic'])) {
        $data['bic'] = 'NOTPROVIDED';
      }
      $paymentContractId = NULL;
      switch ($mode) {
        case 'RCUR':
          $sepaMandate = CRM_Contract_SepaLogic::createNewMandate([
            'type' => 'RCUR',
            'contact_id' => $contactId,
            'amount' => CRM_Contract_SepaLogic::formatMoney($data['payment_amount']),
            'currency' => CRM_Contract_SepaLogic::getCreditor()->currency,
            'start_date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'creation_date' => date('YmdHis'),
            'date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'validation_date' => date('YmdHis'),
            'iban' => $data['iban'] ?? '',
            'bic' => $data['bic'] ?? '',
            'account_holder' => $data['account_holder'] ?? '',
            'campaign_id' => $data['campaign_id'] ?? NULL,
            'financial_type_id' => 2,
            'frequency_unit' => 'month',
            'cycle_day' => $data['cycle_day'],
            'frequency_interval' => (int) (12 / max(1, (int) ($data['payment_frequency'] ?? 1))),
          ]);
          $paymentContractId = (int) $sepaMandate['entity_id'];
          break;
        case 'None':
          $rc = [
            'contact_id' => $contactId,
            'amount' => 0,
            'currency' => CRM_Contract_SepaLogic::getCreditor()->currency,
            'start_date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'create_date' => date('YmdHis'),
            'date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'validation_date' => date('YmdHis'),
            'account_holder' => $data['account_holder'] ?? '',
            'campaign_id' => $data['campaign_id'] ?? '',
            'payment_instrument_id' => CRM_Contract_Configuration::getPaymentInstrumentIdByName('None'),
            'financial_type_id' => 2,
            'frequency_unit' => 'month',
            'cycle_day' => $data['cycle_day'],
            'frequency_interval' => 1,
            'checkPermissions' => TRUE,
          ];
          CRM_Contract_CustomData::resolveCustomFields($rc);
          $newRc = civicrm_api3('ContributionRecur', 'create', $rc);
          $paymentContractId = (int) $newRc['id'];
          break;
        case 'select':
          $paymentContractId = (int) ($data['recurring_contribution'] ?? 0);
          break;
        case 'nochange':
          $paymentContractId = NULL;
          break;
        default:
          $rc = [
            'contact_id' => $contactId,
            'amount' => CRM_Contract_SepaLogic::formatMoney($data['payment_amount']),
            'currency' => CRM_Contract_SepaLogic::getCreditor()->currency,
            'start_date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'create_date' => date('YmdHis'),
            'date' => CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s'),
            'validation_date' => date('YmdHis'),
            'account_holder' => $data['account_holder'] ?? '',
            'campaign_id' => $data['campaign_id'] ?? '',
            'payment_instrument_id' => CRM_Contract_Configuration::getPaymentInstrumentIdByName($mode),
            'financial_type_id' => 2,
            'frequency_unit' => 'month',
            'cycle_day' => $data['cycle_day'],
            'frequency_interval' => (int) (12 / max(1, (int) ($data['payment_frequency'] ?? 1))),
            'checkPermissions' => TRUE,
          ];
          CRM_Contract_CustomData::resolveCustomFields($rc);
          $newRc = civicrm_api3('ContributionRecur', 'create', $rc);
          $paymentContractId = (int) $newRc['id'];
          break;
      }
      $p = [];
      $p['contact_id'] = $contactId;
      $p['membership_type_id'] = (int) $data['membership_type_id'];
      $p['start_date'] = CRM_Utils_Date::processDate($data['start_date'], NULL, NULL, 'Y-m-d H:i:s');
      $p['join_date'] = CRM_Utils_Date::processDate($data['join_date'] ?? NULL, NULL, NULL, 'Y-m-d H:i:s');
      if (!empty($data['end_date'])) {
        $p['end_date'] = CRM_Utils_Date::processDate($data['end_date'], NULL, NULL, 'Y-m-d H:i:s');
      }
      $p['campaign_id'] = $data['campaign_id'] ?? '';
      $p['membership_general.membership_reference'] = $data['membership_reference'] ?? '';
      $p['membership_general.membership_contract'] = $data['membership_contract'] ?? '';
      $p['membership_general.membership_channel'] = $data['membership_channel'] ?? '';
      $p['membership_payment.membership_recurring_contribution'] = $paymentContractId ?: NULL;
      $p['membership_payment.from_name'] = $data['account_holder'] ?? '';
      $p['note'] = $data['activity_details'] ?? '';
      $p['medium_id'] = $data['activity_medium'] ?? '';
      CRM_Contract_CustomData::resolveCustomFields($p);
      $r = civicrm_api3('Contract', 'create', $p);
      $tx->commit();
      return $r;
    } catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
  }

  public function modify(int $id, array $data): array {
    $tx = new CRM_Core_Transaction();
    try {
      $action = $data['action'] ?? ($data['modify_action'] ?? '');
      $p = [];
      $p['id'] = $id;
      $p['action'] = $action;
      $p['medium_id'] = $data['activity_medium'] ?? '';
      $p['note'] = $data['activity_details'] ?? '';
      if (!empty($data['activity_date'])) {
        $p['date'] = CRM_Utils_Date::processDate($data['activity_date'], $data['activity_date_time'] ?? NULL, FALSE, 'Y-m-d H:i:s');
      }
      if (in_array($action, ['update', 'revive'], TRUE)) {
        $types = CRM_Contract_Configuration::getSupportedPaymentTypes(TRUE);
        $m = civicrm_api3('Membership', 'getsingle', ['id' => $id]);
        $contactId = (int) $m['contact_id'];
        $opt = $data['payment_option'] ?? '';
        switch ($opt) {
          case 'select':
            $rcId = (int) ($data['recurring_contribution'] ?? 0);
            $p['membership_payment.membership_recurring_contribution'] = $rcId;
            $rc = civicrm_api3('ContributionRecur', 'getsingle', [
              'id' => $rcId,
              'return' => ['amount','frequency_unit','frequency_interval','cycle_day','payment_instrument_id'],
            ]);
            $freq = ($rc['frequency_unit'] === 'month') ? (int) (12 / max(1, (int) $rc['frequency_interval'])) : (int) (1 / max(1, (int) $rc['frequency_interval']));
            $annual = CRM_Contract_SepaLogic::formatMoney(((float) $rc['amount']) * $freq);
            $p['membership_payment.membership_annual'] = $annual;
            $p['membership_payment.membership_frequency'] = $freq;
            $p['membership_payment.cycle_day'] = (int) ($rc['cycle_day'] ?? 0);
            $p['membership_payment.payment_instrument'] = (int) ($rc['payment_instrument_id'] ?? 0);
            break;
          case 'nochange':
            break;
          case 'None':
            $pi = $types['None'] ?? CRM_Contract_Configuration::getPaymentInstrumentIdByName('None');
            if ($pi) {
              $p['membership_payment.payment_instrument'] = $pi;
              $p['contract_updates.ch_payment_instrument'] = $pi;
            }
            break;
          case 'RCUR':
            $amount = CRM_Contract_SepaLogic::formatMoney($data['payment_amount'] ?? 0);
            $annual = CRM_Contract_SepaLogic::formatMoney(((int) ($data['payment_frequency'] ?? 0)) * $amount);
            $from_ba = CRM_Contract_BankingLogic::getOrCreateBankAccount($contactId, $data['iban'] ?? '', ($data['bic'] ?? '') ?: 'NOTPROVIDED');
            $pi = $types['RCUR'] ?? CRM_Contract_Configuration::getPaymentInstrumentIdByName('RCUR');
            if ($pi) {
              $p['contract_updates.ch_payment_instrument'] = $pi;
            }
            $p['contract_updates.ch_annual'] = $annual;
            $p['contract_updates.ch_frequency'] = $data['payment_frequency'] ?? '';
            $p['contract_updates.ch_cycle_day'] = $data['cycle_day'] ?? '';
            $p['contract_updates.ch_from_ba'] = $from_ba;
            $p['contract_updates.ch_from_name'] = $data['account_holder'] ?? '';
            $p['contract_updates.ch_defer_payment_start'] = empty($data['defer_payment_start']) ? '0' : '1';
            break;
          default:
            $newOpt = $opt;
            $pi = $types[$newOpt] ?? '';
            if ($pi) {
              $p['membership_payment.payment_instrument'] = $pi;
              $p['contract_updates.ch_payment_instrument'] = $pi;
            }
            $annual = CRM_Contract_SepaLogic::formatMoney(((int) ($data['payment_frequency'] ?? 0)) * CRM_Contract_SepaLogic::formatMoney($data['payment_amount'] ?? 0));
            $p['membership_payment.membership_annual'] = $annual;
            $p['membership_payment.membership_frequency'] = $data['payment_frequency'] ?? '';
            $p['membership_payment.cycle_day'] = $data['cycle_day'] ?? '';
            $p['membership_payment.to_ba'] = CRM_Contract_BankingLogic::getCreditorBankAccount();
            $p['membership_payment.from_ba'] = CRM_Contract_BankingLogic::getOrCreateBankAccount($contactId, $data['iban'] ?? '', $data['bic'] ?? '');
            $p['membership_payment.from_name'] = $data['account_holder'] ?? '';
            $p['membership_payment.defer_payment_start'] = empty($data['defer_payment_start']) ? '0' : '1';
            break;
        }
        if (!empty($data['membership_type_id'])) {
          $p['membership_type_id'] = (int) $data['membership_type_id'];
        }
        if (array_key_exists('campaign_id', $data)) {
          $p['campaign_id'] = $data['campaign_id'];
        }
      } elseif ($action === 'cancel') {
        $p['membership_cancellation.membership_cancel_reason'] = $data['cancel_reason'] ?? '';
      } elseif ($action === 'pause') {
        $p['resume_date'] = CRM_Utils_Date::processDate($data['resume_date'] ?? NULL, FALSE, FALSE, 'Y-m-d');
      }
      CRM_Contract_CustomData::resolveCustomFields($p);
      $r = civicrm_api3('Contract', 'modify', $p);
      civicrm_api3('Contract', 'process_scheduled_modifications', ['id' => $id]);
      $tx->commit();
      return $r;
    } catch (\Throwable $e) {
      $tx->rollback();
      throw $e;
    }
  }
}
