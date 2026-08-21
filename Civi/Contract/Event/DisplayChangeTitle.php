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
use Civi\Contract\ContractChange\ContractChangeTypeContainer;
use Civi\Contract\ContractChange\SchedulableContractChangeInterface;

/**
 * Class DisplayChangeTitle
 *
 * @note  currently, this doesn't work during the creation of the change activities,
 *   because the symfony events cause havoc there
 *
 * Allows extensions to provide a custom renderer for
 *  the subjects of change events
 *
 * @package Civi\Contract\Event
 */
class DisplayChangeTitle extends AbstractConfigurationEvent {
  public const EVENT_NAME = 'de.contract.renderchangedisplay';

  /**
   * The change activity ID
   */
  protected int $change_activity_id;

  /**
   * The change activity type ID
   */
  protected int $change_activity_type_id;

  /**
   * The change activity data
   */
  protected ?array $change_activity_data = NULL;

  /**
   * The change's display title
   */
  protected ?string $change_activity_display_title = NULL;

  /**
   * The change's hover title
   */
  protected ?string $change_activity_display_hover_title = NULL;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param int $change_activity_type_id
   *   the change activity type ID
   *
   * @param int $change_activity_id
   *   the change activity
   */
  public function __construct(int $change_activity_type_id, int $change_activity_id) {
    $this->change_activity_id = $change_activity_id;
    $this->change_activity_type_id = $change_activity_type_id;
  }

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param int $change_activity_type_id
   *   the change activity type ID
   *
   * @param int $change_activity_id
   *   the change activity
   */
  public static function renderDisplayChangeTitleAndHoverText(
    int $change_activity_type_id,
    int $change_activity_id
  ): self {
    $event = new DisplayChangeTitle($change_activity_type_id, $change_activity_id);
    Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event;
  }

  /**
   * Get the preferred display title
   *
   * @return string
   */
  public function getDisplayTitle(): string {
    if ($this->change_activity_display_title !== NULL) {
      return $this->change_activity_display_title;
    }
    else {
      // Default is activity type label.
      return $this->getActivityClass()::getTitle();
    }
  }

  /**
   * Set the preferred display title
   *
   * @param $title
   *   the display title to be displayed for this activity
   */
  public function setDisplayTitle(string $title): void {
    $this->change_activity_display_title = $title;
  }

  /**
   * Get the preferred display title
   *
   * @return string
   */
  public function getDisplayHover(): string {
    if ($this->change_activity_display_hover_title !== NULL) {
      return $this->change_activity_display_hover_title;
    }
    else {
      // default is display title
      return $this->getDisplayTitle();
    }
  }

  /**
   * Set the preferred display title hover text
   *
   * @param $title
   *   the display hover title to be displayed for this activity
   */
  public function setDisplayHoverTitle(string $title): void {
    $this->change_activity_display_hover_title = $title;
  }

  /**
   * Get the activity data
   *
   * @return array activity data
   */
  public function getChangeActivityData(): array {
    if (empty($this->change_activity_data) && !empty($this->getActivityID())) {
      // todo: isn't that cached somewhere?
      // @phpstan-ignore assign.propertyType
      $this->change_activity_data = civicrm_api3('Activity', 'getsingle', ['id' => $this->getActivityID()]);
      \CRM_Contract_CustomData::labelCustomFields($this->change_activity_data);
    }
    return $this->change_activity_data ?? [];
  }

  /**
   * Returns true if the action is scheduled, or false if it's already been executed or cancelled
   *
   * @return bool is scheduled
   */
  public function isActionScheduled(): bool {
    $data = $this->getChangeActivityData();
    // Completed
    return ($data['status_id'] != 2);
  }

  /**
   * Get the ID of the change activity
   *
   * @return int
   */
  public function getActivityID(): int {
    return $this->change_activity_id;
  }

  /**
   * Get the ID of the change activity
   *
   * @return int
   */
  public function getActivityTypeID(): int {
    return $this->change_activity_type_id;
  }

  /**
   * @return class-string<\Civi\Contract\ContractChange\ContractChangeInterface>
   *
   * @throws \CRM_Core_Exception
   */
  public function getActivityClass(): string {
    return ContractChangeTypeContainer::getInstance()->getClassForActivityTypeId($this->change_activity_type_id);
  }

  /**
   * @throws \CRM_Core_Exception
   */
  public function getActivityAction(): ?string {
    $class = $this->getActivityClass();

    return is_a($class, SchedulableContractChangeInterface::class, TRUE) ? $class::getActionName() : NULL;
  }

}
