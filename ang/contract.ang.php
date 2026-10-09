<?php

declare(strict_types = 1);

// Angular module contract.
// @see https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
return [
  'js' => [
    'ang/contract.js',
    'ang/contract/*.js',
    'ang/contract/*/*.js',
  ],
  'css' => [],
  'partials' => ['ang/contract'],
  'requires' => ['crmUi', 'crmUtil', 'api4'],
  'settings' => [],
];
