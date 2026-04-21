<?php

declare(strict_types = 1);

namespace Civi\Contract\ActionProvider\Action;

use Civi\ActionProvider\Parameter\ParameterBagInterface;
use CRM_Contract_ExtensionUtil as E;
use Civi\ActionProvider\Action\AbstractAction;
use Civi\Api4\Campaign;
use Civi\Api4\MembershipType;

abstract class AbstractContractAction extends AbstractAction {

  /**
   * @return array<int, string>
   */
  protected function getFrequencies(): array {
    return [
      1 => E::ts('annually'),
      2 => E::ts('semi-annually'),
      4 => E::ts('quarterly'),
      6 => E::ts('bi-monthly'),
      12 => E::ts('monthly'),
    ];
  }

  /**
   * @return array<int<0, 28>, int<1, 28>|string>
   */
  protected function getCollectionDays(): array {
    $list = range(0, 28);
    $options = array_combine($list, $list);
    $options[0] = E::ts('as soon as possible');
    return $options;
  }

  /**
   * @return array<int, string>
   */
  protected function getMembershipTypes(): array {
    return MembershipType::get(FALSE)
      ->addSelect('id', 'name')
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @return array<int, string>
   */
  protected function getCampaigns(): array {
    return Campaign::get(FALSE)
      ->addSelect('id', 'name')
      ->addWhere('is_active', '=', TRUE)
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

  /**
   * @phpstan-param array<string, array<int, string|null>> $map
   * @phpstan-return array<string, string>
   */
  protected function translateParameterMap(array $map, ParameterBagInterface $parameters): array {
    $params = [];

    foreach ($map as $newName => $target) {

      $name = $target[0];
      $default = $target[1];
      $cast = $target[2] ?? NULL;

      $value = $parameters->getParameter($name) ?? $this->configuration->getParameter($default) ?? NULL;

      //fix default values submitted by form processor
      if ($value === '') {
        $value = NULL;
      }

      if ($value !== NULL) {
        if ($cast === 'int') {
          $value = (int) $value;
        }
        elseif ($cast === 'float') {
          $value = (float) $value;
        }
      }

      $params[$newName] = $value;
    }

    return $params;
  }

}
