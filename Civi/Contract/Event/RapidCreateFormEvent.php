<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class RapidCreateFormEvent
 *
 * Allows extensions to provide/customise the rapid create form,
 *   which is triggered when a new membership is to be created with a new contact
 *
 * @package Civi\RemoteEvent\Event
 */
class RapidCreateFormEvent extends ConfigurationEvent
{
  public static string $event_name = 'de.contract.rapidcreateform';

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
   * @return string|null
   */
  public function getRapidCreateFormUrl()
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
    \Civi::dispatcher()->dispatch(self::$event_name, $event);
    return $event->getRapidCreateFormUrl();
  }
}
