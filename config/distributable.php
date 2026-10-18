<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    | Every module of the application, by name: apps/Iam is `iam`. What the
    | package can read from a module's folder (its namespace, its provider,
    | whether it has a database) is never declared here. `host` is the URL of
    | a module when it runs in another process, or ['url' => …, 'transport' => …].
    */

    'declared' => [
        'iam' => ['host' => env('IAM_HOST')],
        'analytics' => [],
        'notifications' => [],
    ],

    // The URL of a module's routes file: {module} its name, {name} the file's (routes/api.php → api).
    // A module sets its own in `declared`: 'iam' => ['prefix' => 'identity/{name}']. Change one, and
    // change its rules in gateway/traefik/dynamic/routers.yml too: Traefik routes by these paths.
    'routes' => [
        'prefix' => '{module}/{name}',
    ],

    // Modules booted by THIS process: '*' = all, or a comma-separated list.
    'runs' => env('RUN_MODULES', '*'),

    // A module is {path}/{Module}, its classes {path}/{Module}/app under {namespace}\{Module}\, unless
    // it declares its own path and namespace. No entry to add to composer.json.
    'modules' => [
        'path' => 'apps',
        'namespace' => 'Apps',
    ],

    // What module iam shares is {path}/Iam, under {namespace}\Iam\.
    'foundation' => [
        'path' => 'foundation',
        'namespace' => 'Foundation',
    ],

    // Path answering which modules this process runs (e.g. '/'), or null to register nothing.
    'status_route' => env('MODULES_STATUS_ROUTE'),

];
