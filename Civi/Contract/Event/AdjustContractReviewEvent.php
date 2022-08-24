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
 * Class AdjustContractReviewEvent
 *
 * Allows customisation of the review screen
 *
 * @package Civi\Contract\Event
 */
class AdjustContractReviewEvent extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.contractreview.adjust';

  /** @var array list of column indices to hide in the view */
  protected $hide_columns;

  protected function __construct()
  {
    $this->hide_columns = [];
  }

  /**
   * Dispatch the Symfony event to get the review table adjustments
   *
   * @return AdjustContractReviewEvent
   */
  public static function getContractReviewAdjustments()
  {
    $event = new AdjustContractReviewEvent();
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event;
  }

  /**
   * Get the list of column indices to be hidden
   *
   * @return array
   */
  public function getHiddenColumnIndices()
  {
    return $this->hide_columns;
  }

  /**
   * Set the list of column indices to be hidden
   *
   * @param array $columns_to_hide
   *  list of integers
   */
  public function setHiddenColumnIndices($columns_to_hide)
  {
    $this->hide_columns = $columns_to_hide;
  }
}
