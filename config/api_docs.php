<?php

declare(strict_types=1);

return [
    // Explicit opt-in: production remains undiscoverable unless an operator enables it.
    'enabled' => (bool) env('SCRAMBLE_ENABLED', false),
    'public_access' => (bool) env('SCRAMBLE_PUBLIC_ACCESS', false),
    'allowed_environments' => array_values(array_filter(array_map('trim', explode(',', (string) env('SCRAMBLE_ALLOWED_ENVIRONMENTS', 'local,testing,staging'))))),
];
