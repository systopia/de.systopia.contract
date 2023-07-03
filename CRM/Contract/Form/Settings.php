<?php

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Settings extends CRM_Core_Form{
  function buildQuickForm(){
    $this->addEntityRef(
      'contract_modification_reviewers',
      E::ts('Contract modification reviewers'),
      ['multiple' => 'multiple']);

    $this->add(
      'select',
      'date_adjustment',
      E::ts('Adjust Incoming Date'),
      [
        '' => E::ts("don't"),
        '1 hour' => E::ts("up to 1 hour"),
        '2 hours' => E::ts("up to 2 hours"),
        '6 hour' => E::ts("up to 6 hours"),
        '12 hour' => E::ts("up to 12 hours"),
        '1 day' => E::ts("up to 1 day"),
        '2 day' => E::ts("up to 2 days"),
        'always' => E::ts("always"),
      ]
    );

    $this->addButtons([
      array('type' => 'cancel', 'name' => E::ts('Back')),
      array('type' => 'submit', 'name' => E::ts('Save'))
    ]);
    $this->setDefaults();
  }

  function setDefaults($defaultValues = null, $filter = null){
    parent::setDefaults([
      'contract_modification_reviewers' => Civi::settings()->get('contract_modification_reviewers'),
      'date_adjustment' => Civi::settings()->get('date_adjustment'),
    ]);
  }



  function postProcess(){
    $submitted = $this->exportValues();
    Civi::settings()->set('contract_modification_reviewers', $submitted['contract_modification_reviewers']);
    Civi::settings()->set('date_adjustment', $submitted['date_adjustment']);
    CRM_Core_Session::setStatus( E::ts('Contract settings updated.'), E::ts("Success"), 'success');
  }

  /**
   * Set the given execution time to "today"
   *
   * @param string $requested_execution_time
   * @return string adjusted execution time
   */
  public static function adjustRequestedExecutionTime($requested_execution_time)
  {
    // first check, if the date is before midnight today:
    $today = strtotime('today');
    $requested_execution_time_term = date('Y-m-d H:i:s', $requested_execution_time);
    if ($requested_execution_time < $today) {
      // this would cause an error, so let's see if it's in the configured range to adjust
      $grace_term = Civi::settings()->get('date_adjustment');

      // cases for the different settings options
      switch ($grace_term) {
        case null:
        case '':
          // do nothing
          return $requested_execution_time;

        case 'always':
          return max($requested_execution_time, $today);

        default:
          // the grace term should now be a relative time term like '1 day'
          $adjusted_execution_time_term = date('Y-m-d H:i:s', $requested_execution_time) . " + {$grace_term}";
          $adjusted_execution_time = strtotime($adjusted_execution_time_term);
          if ($adjusted_execution_time >= $today) {
            // with the grace period added, this is due now, so run it:
            return $today;
          } else {
            // do nothing
            return $requested_execution_time;
          }
      }
    }
    return $requested_execution_time;
  }
}
