<?php

return [
    'api_endpoint' => env('KEA_API_ENDPOINT', 'http://localhost:8000'),
    'servers' => [
        [
            'name' => 'primary',
            'url' => env('KEA_PRIMARY_URL', 'http://kea-primary:8000'),
        ],
        [
            'name' => 'secondary',
            'url' => env('KEA_SECONDARY_URL', 'http://kea-secondary:8000'),
        ],
    ],
];