<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Class ConfigurationEvent
 *
 * @package Civi\Contract\Event
 *
 * Abstract event class to provide some basic functions
 */
abstract class AbstractConfigurationEvent extends Event {
}
