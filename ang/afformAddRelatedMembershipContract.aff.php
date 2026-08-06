<?php
use CRM_Contract_ExtensionUtil as E;

return [
  'type' => 'form',
  'title' => E::ts('Related Membership (Contract)'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/contract/related-membership/add',
  'create_submission' => TRUE,
  'locale' => ['en_US'],
];
