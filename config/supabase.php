<?php

$legacyConfig = [];

$localConfigPath = base_path('server/supabase_config.php');
$parentConfigPath = base_path('../server/supabase_config.php');

if (is_file($localConfigPath)) {
    $legacyConfig = @include $localConfigPath;
} elseif (is_file($parentConfigPath)) {
    $legacyConfig = @include $parentConfigPath;
}

if (!is_array($legacyConfig)) {
    $legacyConfig = [];
}

return [
    'url' => env('SUPABASE_URL', $legacyConfig['url'] ?? ''),
    'service_role_key' => env('SUPABASE_SERVICE_ROLE_KEY', $legacyConfig['service_role_key'] ?? ''),
    'anon_key' => env('SUPABASE_ANON_KEY', $legacyConfig['anon_key'] ?? ''),
];
