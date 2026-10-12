<?php

/*
| Only the two React apps may call the API from a browser. Never "*" in production.
| FRONTEND_URL / ADMIN_URL may contain several comma-separated origins.
*/
$origins = array_values(array_filter(array_map('trim', array_merge(
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')),
    explode(',', (string) env('ADMIN_URL', 'http://localhost:5174')),
    explode(',', (string) env('CORS_EXTRA_ORIGINS', '')),
))));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With', 'Origin'],
    'exposed_headers' => ['Content-Disposition', 'Retry-After'],
    'max_age' => 7200,
    'supports_credentials' => false,
];
