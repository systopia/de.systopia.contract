<?php
use CRM_Contract_ExtensionUtil as E;

return [
  'type' => 'search',
  'title' => E::ts('Related Memberships'),
  'icon' => 'fa-list-alt',
  'server_route' => 'civicrm/membership/related',
];
