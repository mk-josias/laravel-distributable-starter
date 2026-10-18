<?php

use Foundation\Analytics\Enums\AnalyticsPermission;
use Foundation\Common\Auth\LocalPrincipals;
use Foundation\Iam\Auth\IamPermissions;
use Foundation\Iam\Shadows\UserShadow;

return [

    // Authenticate sets the guard user itself: the provider never loads one, so it names no model.
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL'),
        ],
    ],

    // The gateway checks the client's token and sends X-Identity, its claims signed with this secret: a module reads only that header.
    'gateway' => [
        'header' => 'X-Identity',
        'secret' => env('AUTH_GATEWAY_SECRET'),
    ],

    // How the gateway checks the client's token, and so what iam issues: `signature`, a JWT checked with iam's
    // public key; `issuer`, an opaque token iam is asked about on every request. The same for iam and the gateway.
    'tokens' => [
        'validation' => env('AUTH_TOKEN_VALIDATION_STRATEGY', 'signature'),
        // Seconds a token lives. Under `signature` it is also the longest a logout takes to apply: see the README.
        'ttl' => (int) env('AUTH_TOKEN_TTL', 3600),
        // `signature` only: seconds a refresh token lives, one per device, replaced on each use; 0 issues none.
        'refresh_ttl' => (int) env('AUTH_REFRESH_TOKEN_TTL', 0),
        'jwt' => [
            // Where `php artisan auth:jwt-keys` writes the pair; the gateway reads the public one there too.
            'path' => $keys = env('AUTH_JWT_KEYS_PATH') ?: storage_path(),
            // The PEM itself, or else the file in `path`.
            'public_key' => env('AUTH_JWT_PUBLIC_KEY') ?: (is_file($public = "{$keys}/jwt-public.key") ? file_get_contents($public) : null),
            'private_key' => env('AUTH_JWT_PRIVATE_KEY') ?: (is_file($private = "{$keys}/jwt-private.key") ? file_get_contents($private) : null),
        ],
    ],

    // Where the user of a request comes from: LocalPrincipals reads the module's own database, ClaimsPrincipals only the token, RpcPrincipals asks iam.
    'principal_resolver' => env('AUTH_PRINCIPAL_RESOLVER', LocalPrincipals::class),

    // The model LocalPrincipals reads users from: iam's copy, kept by the modules that list it in $shadows; iam sets its User.
    'principal' => UserShadow::class,

    // Who says what a user may do: IamPermissions asks iam, ClaimsPermissions reads the claim named below in the token.
    'permission_source' => env('AUTH_PERMISSION_SOURCE', IamPermissions::class),
    'permissions_claim' => 'permissions',

    // The permission enums of every module, in the foundation so iam knows them wherever it runs: iam:sync-permissions creates them.
    'permissions' => [
        AnalyticsPermission::class,
    ],

];
