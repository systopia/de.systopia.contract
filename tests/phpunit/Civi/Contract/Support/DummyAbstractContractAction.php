<?php

declare(strict_types = 1);

namespace Civi\Contract\Support;

use Civi\ActionProvider\Parameter\ParameterBag;
use Civi\ActionProvider\Parameter\ParameterBagInterface;
use Civi\Contract\ActionProvider\Action\AbstractContractAction;

final class DummyAbstractContractAction extends AbstractContractAction {

  /**
   * @param array<string, mixed> $values
   */
  public function setTestConfiguration(array $values): void {
    $configuration = new ParameterBag();

    foreach ($values as $name => $value) {
      $configuration->setParameter($name, $value);
    }

    $this->configuration = $configuration;
  }

  /**
   * @param array<string, array<int, string|null>> $map
   *
   * @return array<string, mixed>
   */
  public function runTranslateParameterMap(array $map, ParameterBagInterface $parameters): array {
    return $this->translateParameterMap($map, $parameters);
  }

  /**
   * @return array<int, string>
   */
  public function runGetFrequencies(): array {
    return $this->getFrequencies();
  }

  /**
   * @return array<int<0, 28>, int<1, 28>|string>
   */
  public function runGetCollectionDays(): array {
    return $this->getCollectionDays();
  }

  public function getConfigurationSpecification() {
    return new \Civi\ActionProvider\Parameter\SpecificationBag([]);
  }

  public function getParameterSpecification() {
    return new \Civi\ActionProvider\Parameter\SpecificationBag([]);
  }

  public function getOutputSpecification() {
    return new \Civi\ActionProvider\Parameter\SpecificationBag([]);
  }

  protected function doAction(ParameterBagInterface $parameters, ParameterBagInterface $output): void {
  }

  /**
   * @return array<int, string>
   */
  public function runGetMembershipTypes(): array {
    return $this->getMembershipTypes();
  }

  /**
   * @return array<int, string>
   */
  public function runGetCampaigns(): array {
    return $this->getCampaigns();
  }

}
