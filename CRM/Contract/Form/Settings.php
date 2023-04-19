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
   * @param string $datetime
   * @return string adjusted datetime
   */
  public static function adjustRequestedExecutionTime($datetime)
  {
    // first check, if the date is before midnight today:
    if ($datetime < strtotime('today')) {
      // this would cause an error, so let's see if it's in the configured range to adjust
      $date_offset_cutoff = Civi::settings()->get('date_adjustment');

      // cases for the different settings options
      switch ($date_offset_cutoff) {
        case 'always':
          return strtotime('today');

        case null:
        case '':
          // do nothing
          return $datetime;

        default:
          // the date_offset_cutoff should be a relative time term
          if ($datetime >= strtotime("now - {$date_offset_cutoff}")) {
            // this is within the specified range: adjust and return
            return strtotime('today');
          } else {
            // do nothing
            return $datetime;
          }
      }
    }
  }
}
