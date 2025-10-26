<?php

return [
    // Global RADIUS shared secret for all NAS clients (802.1X)
    // If set, this secret will be used for all RADIUS clients
    // Leave empty to use individual secrets per client
    'global_secret' => env('RADIUS_GLOBAL_SECRET', ''),
    
    // Default type for RADIUS clients
    'default_type' => env('RADIUS_DEFAULT_TYPE', 'other'),
    
    // Auto-generate secrets for new clients if global secret is not set
    'auto_generate_secrets' => env('RADIUS_AUTO_GENERATE_SECRETS', true),
    
    // Multiple FreeRADIUS server database connections
    // Each server will have the RADIUS clients synced to its database
    'servers' => [
        [
            'name' => 'FreeRADIUS Primary',
            'enabled' => env('RADIUS_PRIMARY_ENABLED', true),
            'host' => env('RADIUS_PRIMARY_HOST', 'localhost'),
            'port' => env('RADIUS_PRIMARY_PORT', 3306),
            'database' => env('RADIUS_PRIMARY_DB', 'radius'),
            'username' => env('RADIUS_PRIMARY_USER', 'radius'),
            'password' => env('RADIUS_PRIMARY_PASS', ''),
            'charset' => 'utf8mb4',
        ],
        [
            'name' => 'FreeRADIUS Secondary',
            'enabled' => env('RADIUS_SECONDARY_ENABLED', true),
            'host' => env('RADIUS_SECONDARY_HOST', 'localhost'),
            'port' => env('RADIUS_SECONDARY_PORT', 3306),
            'database' => env('RADIUS_SECONDARY_DB', 'radius'),
            'username' => env('RADIUS_SECONDARY_USER', 'radius'),
            'password' => env('RADIUS_SECONDARY_PASS', ''),
            'charset' => 'utf8mb4',
        ],
    ],
];
