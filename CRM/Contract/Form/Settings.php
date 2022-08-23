<?php

use CRM_Contract_ExtensionUtil as E;

class CRM_Contract_Form_Settings extends CRM_Core_Form{
  function buildQuickForm(){
    $this->addEntityRef('contract_modification_reviewers', E::ts('Contract modification reviewers'), ['multiple' => 'multiple']);
    $this->addButtons([
      array('type' => 'cancel', 'name' => E::ts('Back')),
      array('type' => 'submit', 'name' => E::ts('Save'))
    ]);
    $this->setDefaults();
  }

  function setDefaults($defaultValues = null, $filter = null){
    $defaults = [
      'contract_modification_reviewers' => civicrm_api3('Setting', 'GetValue', [
        'name' => 'contract_modification_reviewers',
        'group' => 'Contract preferences'
      ]),
    ];
    parent::setDefaults($defaults);
  }



  function postProcess(){
    $submitted = $this->exportValues();
    civicrm_api3('Setting', 'Create', ['contract_modification_reviewers' => $submitted['contract_modification_reviewers']]);
    CRM_Core_Session::setStatus( E::ts('Contract settings updated.'), null, 'success');
  }
}
