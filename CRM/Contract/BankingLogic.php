<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2017-2019 SYSTOPIA                             |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

/**
 * Interface to CiviBanking functions
 *
 * @todo resolve hard dependecy to CiviBanking module
 */
class CRM_Contract_BankingLogic {

  /**
   * cached value for self::getCreditorBankAccount() */
  protected static ?int $_creditorBankAccount = NULL;
  protected static ?int $_ibanReferenceType = NULL;

  /**
   * get bank account information
   *
   * @phpstan-return array<string, mixed>
   */
  public static function getBankAccount(?int $account_id): ?array {
    if (NULL === $account_id) {
      return NULL;
    }

    $data = [];
    /**
     * @phpstan-var array{
     *   "id": int,
     *   "contact_id": int,
     *   "data_parsed"?: string
     * } $account
     */
    $account = civicrm_api3('BankingAccount', 'getsingle', ['id' => $account_id]);
    $data['contact_id'] = $account['contact_id'];
    $data['id'] = $account['id'];
    if (isset($account['data_parsed'])) {
      $data_parsed = json_decode($account['data_parsed'], TRUE);
      if (is_array($data_parsed)) {
        foreach ($data_parsed as $key => $value) {
          $data[$key] = $value;
          // also add in lower case to avoid stuff like bic/BIC confusion
          $data[strtolower($key)] = $value;
        }
      }
    }

    // load IBAN reference
    /** @phpstan-var array<string, mixed> $reference */
    $reference = civicrm_api3('BankingAccountReference', 'getsingle', [
      'ba_id'             => $account_id,
      'reference_type_id' => self::getIbanReferenceTypeID(),
    ]);
    $data['iban'] = $reference['reference'];

    return $data;
  }

  /**
   * Get the ID of the BankingAccount entity representating the
   * submitted contact, IBAN, and BIC.
   * The account will be created if it doesn't exist yet
   *
   * @todo cache results?
   */
  public static function getOrCreateBankAccount(int $contact_id, ?string $iban, ?string $bic): ?int {
    if (!isset($iban) || '' === $iban) {
      return NULL;
    }

    try {
      // find existing references
      /** @phpstan-var array{"values": array<string, mixed>} $existing_references */
      $existing_references = civicrm_api3('BankingAccountReference', 'get', [
        'reference'         => $iban,
        'reference_type_id' => self::getIbanReferenceTypeID(),
        'option.limit'      => 0,
      ]);

      // get the accounts for this
      $bank_account_ids = [];
      foreach ($existing_references['values'] as $account_reference) {
        $bank_account_ids[] = $account_reference['ba_id'];
      }
      if ([] !== $bank_account_ids) {
        /** @phpstan-var array{"count": int, "values": array<int, array<string, mixed>>} $contact_bank_accounts */
        $contact_bank_accounts = civicrm_api3('BankingAccount', 'get', [
          'id'           => ['IN' => $bank_account_ids],
          'contact_id'   => $contact_id,
          'option.limit' => 1,
        ]);
        if ($contact_bank_accounts['count'] > 0) {
          // bank account already exists with the contact
          /** @phpstan-var array{"id": int} $account */
          $account = reset($contact_bank_accounts['values']);
          return (int) $account['id'];
        }
      }

      // if we get here, that means that there is no such bank account
      //  => create one
      $data = ['BIC' => $bic, 'country' => substr($iban, 0, 2)];
      /** @phpstan-var array{"id": int} $bank_account */
      $bank_account = civicrm_api3('BankingAccount', 'create', [
        'contact_id'  => $contact_id,
        'description' => 'Bulk Importer',
        'data_parsed' => json_encode($data),
      ]);

      $bank_account_reference = civicrm_api3('BankingAccountReference', 'create', [
        'reference'         => $iban,
        'reference_type_id' => self::getIbanReferenceTypeID(),
        'ba_id'             => $bank_account['id'],
      ]);
      return (int) $bank_account['id'];
    }
    catch (CRM_Core_Exception $e) {
      error_log("Couldn't add bank account '{$iban}' [{$contact_id}]");
      return NULL;
    }
  }

  /**
   * Get the (target) bank account of the creditor
   */
  public static function getCreditorBankAccount(): ?int {
    if (self::$_creditorBankAccount === NULL) {
      $creditor = CRM_Contract_SepaLogic::getCreditor();
      if (NULL !== $creditor) {
        self::$_creditorBankAccount = self::getOrCreateBankAccount(
          (int) $creditor->creditor_id,
          $creditor->iban,
          $creditor->bic
        );
      }
    }
    return self::$_creditorBankAccount;
  }

  /**
   * return the IBAN for the given bank account id if there is one
   */
  public static function getIBANforBankAccount(int $bank_account_id): string {
    /** @phpstan-var array{"count": int, "values": array<string, mixed>} $iban_references */
    $iban_references = civicrm_api3('BankingAccountReference', 'get', [
      'ba_id'             => $bank_account_id,
      'reference_type_id' => self::getIbanReferenceTypeID(),
      'return'            => 'reference',
    ]);
    if ($iban_references['count'] > 0) {
      /** @phpstan-var array{"reference": string} $reference */
      $reference = reset($iban_references['values']);
      return $reference['reference'];
    }
    else {
      return '';
    }
  }

  /**
   * Get the reference type ID for IBAN references (cached)
   */
  public static function getIbanReferenceTypeID(): int {
    if (self::$_ibanReferenceType === NULL) {
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', [
        'value'           => 'IBAN',
        'return'          => 'id',
        'option_group_id' => 'civicrm_banking.reference_types',
        'is_active'       => 1,
      ]);
      /** @phpstan-var array{"id": int} $reference_type_value */
      self::$_ibanReferenceType = (int) $reference_type_value['id'];
    }
    return self::$_ibanReferenceType;
  }

  /**
   * Extract account (IDs) from a recurring contribution by looking at the most recent
   * contribution
   *
   * @param $contribution_recur_id ID of an recurring contribution entity
   * @phpstan-return list<int> (from_ba_id, to_ba_id)
   */
  public static function getAccountsFromRecurringContribution(?int $contribution_recur_id): ?array {
    if (!isset($contribution_recur_id)) {
      return NULL;
    }

    $dao = CRM_Core_DAO::executeQuery("
    SELECT COUNT(*) AS count
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'civicrm_value_contribution_information'
  ");
    $dao->fetch();
    if ($dao->count == 0) {
      return NULL;
    }

    $dao = CRM_Core_DAO::executeQuery("
    SELECT COUNT(*) AS count
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'civicrm_value_contribution_information'
      AND column_name IN ('from_ba', 'to_ba')
  ");
    $dao->fetch();
    if ($dao->count < 2) {
      return NULL;
    }

    try {
      $most_recent_contribution = CRM_Core_DAO::executeQuery("
      SELECT from_ba, to_ba
      FROM civicrm_contribution c
        LEFT JOIN civicrm_value_contribution_information i ON i.entity_id = c.id
      WHERE c.contribution_recur_id = {$contribution_recur_id}
      ORDER BY receive_date DESC
      LIMIT 1
    ");

      if ($most_recent_contribution->fetch()) {
        return [$most_recent_contribution->from_ba, $most_recent_contribution->to_ba];
      }
    }
    catch (Exception $ex) {

    }

    return NULL;
  }

}
