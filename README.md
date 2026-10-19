# laravel-distributable starter

A starter kit for a Laravel project: a Laravel application set up with
[laravel-distributable](https://github.com/mk-josias/laravel-distributable). It comes with three example
modules, `iam`, `analytics` and `notifications`, each with its own database. You can read them to see how a module
is written, then replace them with your own.

```bash
composer create-project mk-josias/laravel-distributable-starter my-app
```

Requires PHP 8.4+, and Redis for the event stream.

`create-project` asks two questions, through `php artisan skeleton:setup`:

| Question | Default | What it changes |
|---|---|---|
| Which example modules do you keep? | all | removes `analytics` or `notifications`: its folders, its declaration, its tests, its `auth.permissions` entry and its Docker service. `iam` always stays: authentication, permissions and the user copies rely on it. |
| How does the gateway validate a token? | `signature` | `AUTH_TOKEN_VALIDATION_STRATEGY` in `.env`, and a random `AUTH_GATEWAY_SECRET`. See [Authentication](docs/authentication.md). |

Without a terminal (`--no-interaction`), it keeps everything and `signature`. It runs once: it then
deletes itself and its line in `composer.json`, so it can never remove a module that has become
yours.

## Running it

```bash
cp .env.example .env
php artisan key:generate
# the RSA pair iam signs tokens with, in storage/jwt-*.key
php artisan auth:jwt-keys
# migrates each module's database
php artisan migrate
# the permissions listed in auth.permissions, see Permissions
php artisan iam:sync-permissions --prune
php artisan serve
```

`composer create-project` runs every step but `serve` itself. The modules use sqlite files by
default (`database/iam.sqlite`, `database/analytics.sqlite`, `database/notifications.sqlite`).
`migrate` creates them.

Register a user in `iam`:

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/register \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"name":"<name>","email":"<email>","password":"<password, 8 characters or more>"}'
```

```json
{"success":true,"data":{"id":1,"name":"<name>"}}
```

Log in to get a token. It lasts an hour (`AUTH_TOKEN_TTL`); one session per device (`X-Device-Id`,
or else the user agent), which a new login there replaces:

```bash
curl -X POST http://127.0.0.1:8000/iam/api/v1/login \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -d '{"email":"<email>","password":"<password>"}'
```

```json
{"success":true,"data":{"token":"<token>","expires_at":1790003600}}
```

`register` accepts 6 requests a minute from one address. `login` answers a wrong password and an
unknown email with the same 401, and 429 after 5 failures for one email from one address, for a
minute; a successful login clears them.

`iam` announces the registration with an event. Start the consumer; it serves `analytics` and
`notifications`, each with its own cursor:

```bash
php artisan stream:consume
```

`analytics` now has a signup and `notifications` a welcome message, and each keeps its own copy of
the user. A module reads only the `X-Identity` a gateway sends, never the token: without one in
front, sign it yourself with the gateway's secret, then send it:

```bash
# the X-Identity of user 1, valid for a minute
php artisan tinker --execute='echo Foundation\Iam\Auth\TrustedIdentity::sign(
    ["sub" => 1], time() + 60, config("auth.gateway.secret"));'
curl http://127.0.0.1:8000/analytics/api/v1/metrics \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
curl http://127.0.0.1:8000/notifications/api/v1/notifications \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
```

The X-Identity lasts a minute. The [Docker](docs/docker.md) setups put Traefik in front, which does it on
every request: there, send the token itself.

`php artisan analytics:compute` builds the hourly datasets (the scheduler runs it every hour), and
`GET /analytics/api/v1/metrics?group=day` reads them.

`analytics_datasets` is partitioned by month on PostgreSQL, MySQL and MariaDB, whichever the
analytics connection uses: `php artisan analytics:prune --keep=12` drops the expired months whole.
On SQLite it is a plain table, and pruning deletes the rows.

## What is in the skeleton

```
config/distributable.php        declares the modules, and where they run when they run elsewhere;
                                calls and events keep the package's defaults; each module
                                declares its handlers in $handlers of its service provider
apps/
├── Iam/              owns the users
├── Analytics/        records signups, computes datasets, keeps a copy of the users
└── Notifications/    a personal inbox, fed by events, keeps a copy of the users
foundation/
├── FoundationServiceProvider.php
├── Common/           base classes every module uses
└── Iam/              what iam shares with the other modules
```

## Where to go next

| You want to | Read |
|---|---|
| read the three modules and `foundation/Common` | [The skeleton](docs/skeleton.md) |
| see how a request is authenticated, or use your own identity provider | [Authentication](docs/authentication.md) |
| declare and check permissions | [Permissions](docs/permissions.md) |
| run one container or one per module | [Docker](docs/docker.md) |
| test a module alone | [Tests](docs/tests.md) |
| add code, a module or a library | [Adding code to a module](docs/adding-code.md) |
| run a module in its own process | [Deploying modules](https://mk-josias.github.io/laravel-distributable/docs/deploying) |

## License

MIT.
