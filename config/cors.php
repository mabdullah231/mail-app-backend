<?php

return [
    // Apply CORS to API routes
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Allow all HTTP methods
    'allowed_methods' => ['*'],

    // Allow Vite dev server origins
    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    // No pattern-based origins
    'allowed_origins_patterns' => [],

    // Allow typical headers used by Axios
    'allowed_headers' => ['*'],

    // No special exposed headers
    'exposed_headers' => [],

    // Preflight cache duration
    'max_age' => 0,

    // We use Bearer tokens, not cookies
    'supports_credentials' => false,
];