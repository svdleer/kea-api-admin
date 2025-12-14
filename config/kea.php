<?php

return [
    'api_endpoint' => env('KEA_API_ENDPOINT', 'http://localhost:8000'),
    // Servers are now configured via database (kea_servers table) and managed through web GUI
    // Legacy fallback servers disabled - use /admin/kea-servers to configure
    'servers' => [],
];