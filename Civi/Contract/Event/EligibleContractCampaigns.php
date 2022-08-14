<?php
/*-------------------------------------------------------------+
| SYSTOPIA Contract Extension                                  |
| Copyright (C) 2022 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


namespace Civi\Contract\Event;

use Symfony\Component\EventDispatcher\Event;
use \CRM_Contract_Change as CRM_Contract_Change;

/**
 * Class EligibleContractCampaigns
 *
 * Allows extensions adjust the list of eligible campaigns
 *
 * @package Civi\Contract\Event
 */
class EligibleContractCampaigns extends ConfigurationEvent
{
  public const EVENT_NAME = 'de.contract.eligible_campaigns';

  /**
   * @var array
   *  the list of eligible campaigns [id => label]
   */
  protected array $campaigns;

  /**
   * Symfony event to allow customisation of a contract change event subject
   *
   * @param array $campaigns
   *   list of suggested activity types to be suppressed
   */
  public function __construct($campaigns = [])
  {
    $this->campaigns = $campaigns;
  }

  /**
   * Allows you to modify the list of campaigns eligible for contracts
   *
   * @param $eligible_campaigns array
   *   list of suggested eligible campaigns to be suppressed
   *
   * @return array
   *   list of eligible campaigns
   */
  public static function getAllEligibleCampaigns($eligible_campaigns = [])
  {
    $event = new EligibleContractCampaigns($eligible_campaigns);
    \Civi::dispatcher()->dispatch(self::EVENT_NAME, $event);
    return $event->campaigns;
  }

  /**
   * Set the list of eligible campaigns
   *
   * @param array $campaigns
   *    list of eligible campaigns [id => title]
   */
  public function setEligibleCampaigns($campaigns)
  {
    $this->campaigns = $campaigns;
  }

  /**
   * Get the list of eligible campaigns
   *
   * return array
   *    the list of eligible campaigns [id => title]
   */
  public function getEligibleCampaigns()
  {
    return $this->campaigns;
  }

  /**
   * Add an eligible campaign
   *
   * @param int $campaign_id
   *   ID of the campaign to add
   *
   * @param string $campaign_title
   *   title of the campaign
   */
  public function addEligibleCampaign($campaign_id, $campaign_title)
  {
    $this->campaigns[$campaign_id] = $campaign_title;
  }

  /**
   * Remove an eligible campaign from the current list
   *
   * @param int $campaign_id
   *   ID of the campaign to remove
   */
  public function removeEligibleCampaign($campaign_id)
  {
    unset($this->campaigns[$campaign_id]);
  }
}
