<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Civi;
use Symfony\Contracts\EventDispatcher\Event;
use CRM_Contract_ExtensionUtil as E;

/**
 * Class RapidCreateFormEvent
 *
 * Allows extensions to provide/customise the rapid create form,
 *   which is triggered when a new membership is to be created with a new contact
 *
 * @package Civi\Contract\Event
 */
class RapidCreateFormEvent extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.rapidcreateform';

  /**
   * Symfony event to allow providing a custom change event
   */
  public function __construct()
  {
    $this->url = null;
  }

  /**
   * @var string URL to a rapid create from - if one exists
   */
  protected $url = null;


  /**
   * Set/override the url for the rapid create form
   *
   * @param string $url
   *    the new url to the form
   *
   * @return string|null
   *    the previously set url
   */
  public function setRapidCreateFormUrl($url)
  {
    $old_url = $this->url;
    $this->url = $url;
    return $old_url;
  }

  /**
   * Get the currently set url for the rapid create form
   *
   * @return string
   */
  public function getRapidCreateFormUrl(): ?string
  {
    return $this->url;
  }

  /**
   * Dispatch the Symfony event and return the resulting url
   *
   * @return string|null
   */
  public static function getUrl()
  {
    $event = new RapidCreateFormEvent();
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    $rapid_create_url = $event->getRapidCreateFormUrl();

    // make sure that we don't end up in the classic form if not rapid create form is defined
    if (empty($rapid_create_url)) {
      \CRM_Core_Session::setStatus(
          E::ts("No form for the quick entry of contact and membership data, please use the 'add membership' form in the contact's membership tab. Please contact an expert should you need such a form."),
          E::ts("'Rapid Create' form not available")
      );
      $rapid_create_url = \CRM_Utils_System::url('civicrm/dashboard');
    }

    return $rapid_create_url;
  }
}
