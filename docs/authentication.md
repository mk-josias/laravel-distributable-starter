# Authentication

Only the gateway checks the client's token. A module reads only `X-Identity`, which the gateway
adds once it has checked the token: the token's claims, signed with `AUTH_GATEWAY_SECRET`.

```
client ── Bearer <token> ──► gateway (gateway/, Traefik) ── X-Identity ──► module
                               checks the token                           checks the signature,
                               signs its claims                           then loads the user
```

`X-Identity` is `{payload}.{hmac}`: the payload is the claims as base64url JSON, `sub` and `exp`
among them, and the hmac is `hex(hmac_sha256(AUTH_GATEWAY_SECRET, payload))`
(`Foundation\Iam\Auth\TrustedIdentity`). It lasts a minute.

A route is protected with the `Foundation\Common\Http\Middleware\Authenticate` middleware:

```php
Route::get('me', [AuthController::class, 'me'])->middleware(Authenticate::class);
```

It answers three questions in turn, the last two by classes `config/auth.php` names:

```
request ─► X-Identity ─► Identity ─► PrincipalResolver ─► Principal ─► PermissionSource ─► Policy
         TrustedIdentity  sub, claims  where is the user?               what may it do?
```

- **`PrincipalResolver`**
  - Config key: `auth.principal_resolver`
  - Shipped: `LocalPrincipals`: the user's row in the running module's own database, through
    `auth.principal`: `UserShadow` in the modules that keep it, `User` in iam, which sets it in its
    `config/auth.php`. `ClaimsPrincipals`: the user is built from the claims, no row.
    `RpcPrincipals`: the user iam returns for the id
  - To add your own: name your class
- **`PermissionSource`**
  - Config key: `auth.permission_source`
  - Shipped: `RpcPermissions`: `IamClient::grants()`. `ClaimsPermissions`: the `permissions` claim
  - To add your own: name your class

A request is refused (401, Laravel's `AuthenticationException`) when its `X-Identity` is missing,
expired or not signed with the secret. With `LocalPrincipals` it is also refused while the module
has no copy of the user: a user who has just registered reaches `analytics` once it has consumed
`iam.user.registered`.

In a controller that extends `Foundation\Common\Http\Controllers\Controller`, `$this->principalId()` returns the id of
the authenticated user.

## How the gateway validates a token

`AUTH_TOKEN_VALIDATION_STRATEGY` says how the gateway checks the client's token. iam reads it too,
to issue the token that strategy checks (`POST /iam/api/v1/login` and `/refresh`): both must read
the same value.

- **`signature` (default)**
  - The token iam issues: a JWT, `sub` = the user id
  - The gateway checks it: with iam's public key, then the cached user
  - Settings: the key pair, in `AUTH_JWT_KEYS_PATH` or `AUTH_JWT_*_KEY`
- **`issuer`**
  - The token iam issues: a Sanctum personal access token; iam stores its hash, one per device
  - The gateway checks it: by asking iam, on every request
  - Settings: none

`AUTH_TOKEN_TTL` is how long a token lives, whichever the strategy. Under `issuer`, a logout
(`POST /iam/api/v1/logout`, or `/logout-all` for every device) stops the token at once, so it
can be long. Under `signature`, the gateway never asks iam: a logout applies only when the JWT
expires, so `AUTH_TOKEN_TTL` is also the longest it takes. Keep it short then, and set
`AUTH_REFRESH_TOKEN_TTL` so the client renews it without asking for the password again: the login
also answers a `refresh_token`, spent once by `POST /iam/api/v1/refresh`.

The gateway asks iam over RPC, with the message `FindUserByToken`: for a valid token of either
kind, iam answers the user and writes it for the gateway to read next time; for any other, nothing.
The gateway signs the `X-Identity` itself.

## The gateway

`gateway/` holds the starter's gateway: Traefik, its configuration and its plugin. Both compose
files run it in front of the modules: see [`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-starter/blob/main/gateway/README.md).

```
client ─► Traefik ─► auth plugin (gateway/plugins/auth)
                       signature  1. the JWT, checked with iam's public key
                                  2. GET gateway:user:{id}, in gateway-cache
                                       present → its claims signed here, iam is not called
                                       missing → iam over RPC (FindUserByToken), writes the key
                       issuer     iam over RPC (FindUserByToken), every time
                     ─► the module, with X-Identity
iam saves or deletes a user ─► forgetUser() ─► the key is gone, the next request asks iam
```

A gateway of your own, in any language, does the same: it forwards `POST /iam/api/v1/register`,
`/login` and `/refresh` as they are, and adds `X-Identity` to every other request. It must also:

- drop any `X-Identity` the client sent;
- never forward `/*/rpc/*`: those routes accept any caller holding the RPC secret;
- be the only way in: anyone who reaches a module with a valid `X-Identity` is that user.

To let the gateway emit events or call the modules itself, see "Programs in other languages" in
the package's README.

## An identity provider outside the application

The modules can be the core of a larger system whose users are managed elsewhere (a Node service,
Keycloak, Auth0). Nothing of iam is needed then: the gateway checks the provider's token and signs
its claims, which say who the user is and what it may do.

```yaml
# gateway/traefik/dynamic/middlewares.yml: the provider's key, and no lookup in iam
publicKey: "-----BEGIN PUBLIC KEY-----…"
checkUser: false
```

```dotenv
# the modules: no quotes, a quoted backslash doesn't parse
AUTH_PRINCIPAL_RESOLVER=Foundation\Common\Auth\ClaimsPrincipals
AUTH_PERMISSION_SOURCE=Foundation\Common\Auth\ClaimsPermissions
```

- **`sub`:** the user's id, an integer or a string (`usr_7f3a`)
- **`permissions` (`auth.permissions_claim`):** the list the policies check, such as
  `analytics.datasets.read`
- **any other claim:** the user is a `ClaimsPrincipal`: `$principal->claim('email')`

`apps/Analytics/tests/Feature/ExternalIdentityTest.php` runs it. To keep the users' names locally
all the same, the provider can feed the modules' copies: see "Being the source of a copy" in the
package's `docs/other-languages.md`.

## Without authentication

The package has no authentication of its own: all of it lives in this starter, in
`foundation/Common/Auth`, `foundation/Common/Contracts/Auth`, `foundation/Common/Http/Middleware/Authenticate.php`,
`foundation/Common/Policies` and `foundation/Iam/Auth`. These use them:

- **`foundation/FoundationServiceProvider.php`:** the three bindings of `register()`
- **`apps/*/routes/api.php`:** the `Authenticate` middleware
- **`foundation/Common/Http/Controllers/Controller.php`, `Http/Requests/ApiRequest.php`:** `use
  HasPrincipal`
- **`apps/Iam/app/Models/User.php`, `foundation/Iam/Shadows/UserShadow.php`:** `IsPrincipal` and
  `implements Principal`
- **`apps/Analytics/app/Policies/DatasetPolicy.php`:** the policy, and `authorize()` in
  `MetricController`
- **`tests/ModuleTestCase.php`, `apps/*/tests/Feature`, `tests/Integration`:** the tests that send a
  token
