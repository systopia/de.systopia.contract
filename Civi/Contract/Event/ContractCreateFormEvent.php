<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Civi;
use CRM_Utils_System;

/**
 * Class ContractCreateFormEvent
 *
 * Allows extensions to provide/customise the generic contract create form,
 *   which is triggered when a new membership is to be created with an existing contact
 *
 * @package Civi\Contract\Event
 */
class ContractCreateFormEvent extends ConfigurationEvent {
  public const EVENT_NAME = 'de.contract.createform';

  /**
   * @var int ID of the contact involved */
  protected $contact_id;

  /**
   * @var string|null URL to a contract create from - if one exists
   */
  protected $url = NULL;

  protected function __construct($contact_id) {
    $this->contact_id = $contact_id;
    $this->url = CRM_Utils_System::url('civicrm/contract/create', 'cid=' . $contact_id, TRUE);
  }

  /**
   * Get the contact ID for the given contract
   *
   * @return int
   */
  public function getContactID(): int {
    return $this->contact_id;
  }

  /**
   * Set/override the url for the contract create form
   *
   * @param string $url
   *    the new url to the form
   *
   * @return string|null
   *   the previously set url
   */
  public function setContractCreateFormUrl($url) {
    $old_url = $this->url;
    $this->url = $url;
    return $old_url;
  }

  /**
   * Get the currently set url for the contract create form
   *
   * @return string
   */
  public function getContractCreateFormUrl(): ?string {
    return $this->url;
  }

  /**
   * Dispatch the Symfony event and return the resulting url
   *
   * @param integer $contact_id
   *   ID of the contact the contract is for
   *
   * @return string
   */
  public static function getUrl($contact_id) {
    $event = new ContractCreateFormEvent($contact_id);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event->getContractCreateFormUrl();
  }

}
