<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

namespace Civi\Contract;

use CRM_Contract_ExtensionUtil as E;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContainerSpecs implements CompilerPassInterface {

  /**
   * Register SEPA Actions
   */
  public function process(ContainerBuilder $container) {
    if (!$container->hasDefinition('action_provider')) {
      return;
    }
    $typeFactoryDefinition = $container->getDefinition('action_provider');
    $typeFactoryDefinition->addMethodCall('addAction', ['CreateContract', 'Civi\Contract\ActionProvider\Action\CreateContract', E::ts('Contract: Create'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['GetContract', 'Civi\Contract\ActionProvider\Action\GetContract', E::ts('Contract: Get'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['GetSepaRcur', 'Civi\Contract\ActionProvider\Action\GetSepaRcur', E::ts('Contract: Get Sepa'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['PauseContract', 'Civi\Contract\ActionProvider\Action\PauseContract', E::ts('Contract: Pause'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['ResumeContract', 'Civi\Contract\ActionProvider\Action\ResumeContract', E::ts('Contract: Resume'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['CancelContract', 'Civi\Contract\ActionProvider\Action\CancelContract', E::ts('Contract: Cancel'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['ReviveContract', 'Civi\Contract\ActionProvider\Action\ReviveContract', E::ts('Contract: Revive'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);
    $typeFactoryDefinition->addMethodCall('addAction', ['UpdateContract', 'Civi\Contract\ActionProvider\Action\UpdateContract', E::ts('Contract: Update'), [
      \Civi\ActionProvider\Action\AbstractAction::SINGLE_CONTACT_ACTION_TAG,
    ],
    ]);

  }

}
