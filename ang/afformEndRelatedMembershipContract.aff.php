<?php
use CRM_Contract_ExtensionUtil as E;

return [
  'type' => 'form',
  'title' => E::ts('End Related Membership (Contract)'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/contract/related-membership/end',
  'locale' => ['en_US'],
];
