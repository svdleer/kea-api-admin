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
];
