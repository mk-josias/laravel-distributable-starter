# The gateway

Traefik, in front of the modules: the only way in. It checks the client's token once, then sends
the module the `X-Identity` it trusts. Both compose files run it on `:8000`.

```
gateway/
├── traefik/
│   ├── traefik.yml              static: the entry point :80, the dynamic/ folder, the local plugins
│   └── dynamic/                 read whole by Traefik
│       ├── routers.yml          POST /iam/api/v1/register, /login, /refresh open; every other /{module}/api behind auth
│       └── middlewares.yml      auth and its settings; Traefik's own middlewares go here too
└── plugins/
    └── auth/                    one plugin, one Go module: github.com/mk-josias/gatewayauth
        ├── plugin.go            Config, New, ServeHTTP: the order of the steps
        ├── jwttoken/            checks a JWT (≙ Foundation\Iam\Auth\JwtTokens)
        ├── rpccache/            reads an answer of the RPC cache (≙ RpcService::read())
        ├── redis/               the least of a Redis client: AUTH, SELECT, GET
        └── trustedidentity/     signs X-Identity (≙ Foundation\Iam\Auth\TrustedIdentity)
```

## What the auth plugin does

```
Authorization: Bearer <token>, checked by AUTH_TOKEN_VALIDATION_STRATEGY:
  signature → a JWT, checked with the issuer's public key
            → GET database:cache:iam:user:{sub}, in Redis database 1
                 present → the token's claims signed here, iam is not called
                 missing → GET iam /identity, which fills the key and signs
  issuer    → an opaque token: GET iam /identity, every time, so a revoked token stops at once
  none, or refused → 401, the module is never reached
→ the module, with X-Identity: {base64url claims}.{hmac}
```

The client's own `X-Identity` is always dropped. The RPC routes (`/{module}/rpc/…`) are not routed.

## The routes (`dynamic/routers.yml`)

Each router sends a path to the container serving it. The paths are the modules' route prefixes,
`distributable.routes.prefix` in `config/distributable.php` (`{module}/{name}`, so `/iam/api`), or a module's
own `distributable.declared.{module}.prefix`. Traefik does not read that config: change a prefix there,
and change the router here.

| Router | Rule | Service | Auth |
|---|---|---|---|
| `iam` | `POST` on `/iam/api/v1/register`, `/login`, `/refresh` | `iam` | none: no identity yet |
| `iam-authenticated` | `PathPrefix(/iam/api)` | `iam` | `auth` |
| `analytics` | `PathPrefix(/analytics/api)` | `analytics` | `auth` |
| `notifications` | `PathPrefix(/notifications/api)` | `notifications` | `auth` |

With `'iam' => ['prefix' => 'identity/{name}']`, the two iam rules become
`/identity/api/v1/register`… and `PathPrefix(/identity/api)`; `service: iam` stays, it names the
container. A module added later gets a router and a service of its own, like `analytics`.

## Settings (`dynamic/middlewares.yml`)

| Setting | Starter's value | What it is |
|---|---|---|
| `strategy` | `AUTH_TOKEN_VALIDATION_STRATEGY`, `signature` | `signature` or `issuer`, the same as iam's |
| `redis`, `redisUser`, `redisPassword` | `redis:6379`, `traefik`, `REDIS_TRAEFIK_PASSWORD` | where the RPC cache is, and as whom |
| `redisDatabase` | `1` | `REDIS_CACHE_DB`: the database of Laravel's cache connection |
| `cachePrefix` | `database:cache:` | `REDIS_PREFIX` then `CACHE_PREFIX` |
| `userKey` | `iam:user:` | the key `IamRpcService::findUser()` caches, without the id |
| `iam` | `http://iam.svc:8000/iam/api/v1/identity` | asked on a miss, and for every token under `issuer` |
| `publicKeyFile`, or `publicKey` | `/keys/jwt-public.key` | the issuer's key, under `signature`: iam writes it to the `jwt-keys` volume |
| `checkUser` | `true` | `false` when the issuer is outside iam: its claims are signed with no lookup |
| `gatewaySecret` | `AUTH_GATEWAY_SECRET` | the secret the modules check X-Identity with |
| `identityTtl` | `60` | how long an X-Identity lasts, in seconds |

## Redis

Traefik reaches Redis as the `traefik` user (the `command` of the `redis` service), which may only
read the cached users: `~database:cache:iam:user:* +get +select`. It cannot write a key, nor read
the event stream. In production, set `REDIS_TRAEFIK_PASSWORD`, or give the RPC cache a Redis of its
own.

## Adding to it

- A middleware Traefik has (`rateLimit`, `headers`, `retry`, `compress`): a few lines in
  `dynamic/middlewares.yml`, then its name in a router's `middlewares`.
- A plugin of your own: a folder under `plugins/` with its `go.mod` and `.traefik.yml`, a line in
  `traefik.yml` under `experimental.localPlugins`, and its folder mounted under
  `/plugins-local/src/<its module>` in the compose files. Traefik runs it with Yaegi: the standard
  library only, no `unsafe`.

## Tests

```bash
cd gateway/plugins/auth && go test ./...
```

`tests/e2e/together-or-apart.sh` runs the journeys through Traefik, in both setups.
