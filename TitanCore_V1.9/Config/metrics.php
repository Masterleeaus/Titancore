<?php
return [
  'enabled' => env('AICORE_METRICS_ENABLED', true),
  'access_token' => env('AICORE_METRICS_TOKEN', null),
  'ttl_seconds' => 600,
];