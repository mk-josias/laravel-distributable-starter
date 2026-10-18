<?php

declare(strict_types=1);

// sqlite by default; with DB_CONNECTION=pgsql, the `iam` database, written by a least-privilege role and migrated by its owner.
$pgsql = [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('IAM_DATABASE', 'iam'),
    'charset' => 'utf8',
    'search_path' => 'public',
    'sslmode' => 'prefer',
];

return env('DB_CONNECTION') === 'pgsql' ? [
    'owner' => 'iam_owner',
    'connections' => [
        'iam' => [...$pgsql, 'username' => (string) env('DB_APP_USERNAME', 'distributable_app'), 'password' => (string) env('DB_APP_PASSWORD', '')],
        'iam_owner' => [...$pgsql, 'username' => (string) env('DB_USERNAME', 'distributable'), 'password' => (string) env('DB_PASSWORD', '')],
    ],
] : [
    'connections' => [
        'iam' => [
            'driver' => 'sqlite',
            'database' => env('IAM_DATABASE', database_path('iam.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ],
];
