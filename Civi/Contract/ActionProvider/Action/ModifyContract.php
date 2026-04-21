<?php

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\ActionProvider\Parameter\Specification;
use Civi\ActionProvider\Parameter\SpecificationBag;
use Civi\Api4\Contract;
use CRM_Contract_ExtensionUtil as E;

class ModifyContract extends AbstractContractAction {

  /**
   * @return \Civi\ActionProvider\Parameter\SpecificationBag
   */
  public function getConfigurationSpecification() {
    return new SpecificationBag([
      new Specification(
        'default_action',
        'String',
        E::ts('Action (default)'),
        FALSE,
        'update',
        NULL,
        $this->getActions(),
        FALSE
      ),
      new Specification(
        'default_membership_type_id',
        'Integer',
        E::ts('Membership Type ID (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getMembershipTypes(),
        FALSE
      ),
      new Specification(
        'default_campaign_id',
        'Integer',
        E::ts('Campaign (default)'),
        FALSE,
        NULL,
        NULL,
        $this->getCampaigns(),
        FALSE
      ),
      new Specification(
        'default_payment_option',
        'String',
        E::ts('Payment Option (default)'),
        FALSE,
        'nochange',
        NULL,
        \CRM_Contract_Configuration::getPaymentOptions(),
        FALSE
      ),
      new Specification(
        'default_payment_frequency',
        'Integer',
        E::ts('Payment Frequency (default)'),
        FALSE,
        12,
        NULL,
        $this->getFrequencies(),
        FALSE
      ),
      new Specification(
        'default_cycle_day',
        'Integer',
        E::ts('Collection Day (default)'),
        FALSE,
        1,
        NULL,
        $this->getCollectionDays(),
        FALSE
      ),
    ]);
  }

  /**
   * @return \Civi\ActionProvider\Parameter\SpecificationBag
   */
  public function getParameterSpecification() {
    return new SpecificationBag([
      new Specification('id', 'Integer', E::ts('Contract ID'), TRUE),

      new Specification(
        'action',
        'String',
        E::ts('Action'),
        FALSE,
        NULL,
        NULL,
        $this->getActions(),
        FALSE
      ),

      new Specification('medium_id', 'Integer', E::ts('Medium'), FALSE),
      new Specification('note', 'String', E::ts('Note'), FALSE),

      new Specification(
        'membership_type_id',
        'Integer',
        E::ts('Membership Type ID'),
        FALSE,
        NULL,
        NULL,
        $this->getMembershipTypes(),
        FALSE
      ),

      new Specification(
        'campaign_id',
        'Integer',
        E::ts('Campaign'),
        FALSE,
        NULL,
        NULL,
        $this->getCampaigns(),
        FALSE
      ),

      new Specification(
        'payment_option',
        'String',
        E::ts('Payment Option'),
        FALSE,
        NULL,
        NULL,
        \CRM_Contract_Configuration::getPaymentOptions(),
        FALSE
      ),

      new Specification(
        'payment_amount',
        'Money',
        E::ts('Payment Amount'),
        FALSE
      ),

      new Specification(
        'payment_frequency',
        'Integer',
        E::ts('Payment Frequency'),
        FALSE,
        NULL,
        NULL,
        $this->getFrequencies(),
        FALSE
      ),

      new Specification(
        'cycle_day',
        'Integer',
        E::ts('Collection Day'),
        FALSE,
        NULL,
        NULL,
        $this->getCollectionDays(),
        FALSE
      ),

      new Specification('iban', 'String', E::ts('IBAN'), FALSE),
      new Specification('bic', 'String', E::ts('BIC'), FALSE),
      new Specification('account_holder', 'String', E::ts('Account Holder'), FALSE),
      new Specification('defer_payment_start', 'String', E::ts('Defer Payment Start'), FALSE),

      new Specification(
        'recurring_contribution',
        'Integer',
        E::ts('Recurring Contribution'),
        FALSE
      ),

      new Specification('cancel_reason', 'String', E::ts('Cancellation Reason'), FALSE),
      new Specification('resume_date', 'Date', E::ts('Resume Date'), FALSE),
    ]);
  }

  /**
   * @return \Civi\ActionProvider\Parameter\SpecificationBag
   */
  public function getOutputSpecification() {
    return new SpecificationBag([
      new Specification('id', 'Integer', E::ts('Contract ID'), FALSE, NULL, NULL, NULL, FALSE),
      new Specification('error', 'String', E::ts('Error Message'), FALSE, NULL, NULL, NULL, FALSE),
    ]);
  }

  /**
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $parameters
   * @param \Civi\ActionProvider\Parameter\ParameterBagInterface $output
   */
  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output): void {
    $params = $this->translateParamsForContractApi($parameters);

    try {
      $result = Contract::modifyFull(FALSE)
        ->setValues($params)
        ->execute()
        ->single();

      $output->setParameter('id', $result['id'] ?? $params['id']);
    }
    catch (\CRM_Core_Exception $e) {
      $output->setParameter('id', '');
      $output->setParameter('error', $e->getMessage());
    }
  }

  /**
   * @return array<string, string>
   */
  protected function getActions(): array {
    return [
      'update' => E::ts('Update'),
      'revive' => E::ts('Revive'),
      'cancel' => E::ts('Cancel'),
      'pause' => E::ts('Pause'),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function translateParamsForContractApi(ParameterBagInterface $parameters): array {

    $map = [
      'id' => ['id', NULL, 'int'],
      'action' => ['action', 'default_action'],
      'medium_id' => ['medium_id', NULL, 'int'],
      'note' => ['note', NULL],
      'membership_type_id' => ['membership_type_id', 'default_membership_type_id', 'int'],
      'campaign_id' => ['campaign_id', 'default_campaign_id', 'int'],
      'payment_option' => ['payment_option', 'default_payment_option'],
      'payment_amount' => ['payment_amount', NULL, 'float'],
      'payment_frequency' => ['payment_frequency', 'default_payment_frequency', 'int'],
      'cycle_day' => ['cycle_day', 'default_cycle_day', 'int'],
      'iban' => ['iban', NULL],
      'bic' => ['bic', NULL],
      'account_holder' => ['account_holder', NULL],
      'defer_payment_start' => ['defer_payment_start', NULL],
      'recurring_contribution' => ['recurring_contribution', NULL, 'int'],
      'cancel_reason' => ['cancel_reason', NULL],
      'resume_date' => ['resume_date', NULL],
    ];

    return $this->translateParameterMap($map, $parameters);
  }

}
