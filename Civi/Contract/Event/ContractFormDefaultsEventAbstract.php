<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

declare(strict_types = 1);

namespace Civi\Contract\Event;

use Civi;

/**
 * Class ContractFormDefaultsEvent
 *
 * Allows an extension to manipulate the default values used for the given contract form
 *
 * The form can be in 'modify' mode (adjusting/replacing an existing payment contract), or
 *   'create' mode (a new contract from scratch)
 *
 * @package Civi\Contract\Event
 */
class ContractFormDefaultsEventAbstract extends AbstractConfigurationEvent {
  public const EVENT_NAME = 'de.contract.form.defaults';

  /**
   * @var string action */
  protected $action;

  /**
   * @var array the form defaults to be manipulated (in place) */
  protected $form_defaults;

  /**
   * Dispatch the Symfony event and return the resulting url
   *
   * @param array $form_defaults
   *   default values for the form
   *
   * @param string $action
   *  the form mode
   */
  public static function adjustDefaults(&$form_defaults, $action) {
    $event = new ContractFormDefaultsEventAbstract($form_defaults, $action);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
  }

  /**
   * Dispatch the Symfony event and return the resulting url
   *
   * @param array $form_defaults
   *   default values for the form
   *
   * @param string $action
   *  the form mode
   */
  protected function __construct(&$form_defaults, $action) {
    $this->action = $action;
    $this->form_defaults = &$form_defaults;
  }

  /**
   * Check if the form is in CREATE mode
   *
   * @return bool
   */
  public function isCreateMode() {
    return $this->action == 'create';
  }

  /**
   * Check if the form is in MODIFY mode
   *
   * @return bool
   */
  public function isModifyMode() {
    // remark: does 'modify' even exist as an action?
    return in_array($this->action, ['cancel', 'pause', 'modify', 'update', 'revive']);
  }

  /**
   *  Get a certain value from the given form
   *
   * @return mixed|null
   */
  public function getFormDefault($field_name) {
    return $this->form_defaults[$field_name] ?? NULL;
  }

  /**
   *  Set a certain value from the given form
   *
   * @param string $field_name
   * @param mixed|null $field_value
   */
  public function setFormDefault($field_name, $field_value) {
    $this->form_defaults[$field_name] = $field_value;
  }

}
