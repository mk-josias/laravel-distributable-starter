<?php

declare(strict_types=1);

// sqlite by default; with DB_CONNECTION=pgsql, the `notifications` database, written by a least-privilege role and migrated by `owner`, the name every module gives its owner role.
$pgsql = [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('NOTIFICATIONS_DATABASE', 'notifications'),
    'charset' => 'utf8',
    'search_path' => 'public',
    'sslmode' => 'prefer',
];

$sqlite = [
    'driver' => 'sqlite',
    'database' => env('NOTIFICATIONS_DATABASE', database_path('notifications.sqlite')),
    'prefix' => '',
    'foreign_key_constraints' => true,
];

return env('DB_CONNECTION') === 'pgsql' ? [
    'default' => 'notifications',
    'connections' => [
        'notifications' => [...$pgsql, 'username' => (string) env('DB_APP_USERNAME', 'distributable_app'), 'password' => (string) env('DB_APP_PASSWORD', '')],
        'owner' => [...$pgsql, 'username' => (string) env('DB_USERNAME', 'distributable'), 'password' => (string) env('DB_PASSWORD', '')],
    ],
] : [
    'default' => 'notifications',
    'connections' => [
        'notifications' => $sqlite,
        'owner' => $sqlite,
    ],
];
