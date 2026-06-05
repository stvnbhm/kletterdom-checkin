<?php

declare(strict_types=1);

return [
    'name'     => $_ENV['APP_NAME']     ?? 'Kletterdom Check-in',
    'env'      => $_ENV['APP_ENV']      ?? 'production',
    'debug'    => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOL),
    'url'      => $_ENV['APP_URL']      ?? '',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Vienna',
    'hash_key' => $_ENV['HASH_KEY']     ?? '',

    'session' => [
        'lifetime_minutes' => (int) ($_ENV['SESSION_LIFETIME_MINUTES'] ?? 120),
        'secure_cookie'    => filter_var($_ENV['SESSION_SECURE_COOKIE'] ?? 'true', FILTER_VALIDATE_BOOL),
        'same_site'        => $_ENV['SESSION_SAME_SITE'] ?? 'Lax',
        'save_path'        => dirname(__DIR__) . '/storage/sessions',
    ],
];
