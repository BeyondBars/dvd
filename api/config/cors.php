<?php

return [
    'paths' => ['*'],
    'allowed_methods' => [
        'POST',
        'GET',
        'OPTIONS',
        'PUT',
        'PATCH',
        'DELETE',
    ],
    // Only the SPA (and the API itself) may make credentialed cross-origin requests.
    'allowed_origins' => array_values(array_filter([
        env('APP_URL', 'http://localhost:8000'),
        env('JS_APP_URL', 'http://localhost:8080'),
        // The customer website(s), comma separated, e.g. https://desivideshidiner.com.au,https://www.desivideshidiner.com.au
        ...array_map('trim', explode(',', (string) env('WEBSITE_URLS', ''))),
    ])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'X-XSRF-TOKEN',
        'X-Requested-With',
        'Content-Type',
        'X-Auth-Token',
        'Origin',
        'Authorization',
        'Cache-Control',
        'X-Conflict-Resolution-Hash',
    ],
    'exposed_headers' => [
        'Content-Disposition',
        'Authorization'
    ],
    'max_age' => 60 * 60 * 24,
    'supports_credentials' => true,
];
