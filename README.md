# laravel-distributable-modules starter

A starter kit for a Laravel project: a Laravel application set up with
[laravel-distributable-modules](https://github.com/mk-josias/laravel-distributable-modules). It comes with three example
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
| How does the gateway validate a token? | `signature` | `AUTH_TOKEN_VALIDATION_STRATEGY` in `.env`, and a random `AUTH_GATEWAY_SECRET`. See [Authentication](#authentication). |

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
front, ask iam for it as the gateway would, then send it yourself:

```bash
# 204, with the X-Identity header
curl -i http://127.0.0.1:8000/iam/api/v1/identity -H 'Authorization: Bearer <token>'
curl http://127.0.0.1:8000/analytics/api/v1/signups \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
curl http://127.0.0.1:8000/notifications/api/v1/notifications \
    -H 'Accept: application/json' -H 'X-Identity: <X-Identity>'
```

The X-Identity lasts a minute. The [Docker](#docker) setups put Traefik in front, which does it on
every request: there, send the token itself.

`php artisan analytics:compute` builds the hourly datasets (the scheduler runs it every hour), and
`GET /analytics/api/v1/datasets?group=day` reads them.

`analytics_datasets` is partitioned by month on PostgreSQL, MySQL and MariaDB, whichever the
analytics connection uses: `php artisan analytics:prune --keep=12` drops the expired months whole.
On SQLite it is a plain table, and pruning deletes the rows.

## What is in the skeleton

```
config/distributable.php        declares the modules, and where they run when they run elsewhere;
                                calls and events keep laravel-microservices' defaults; each module
                                declares its handlers in $handlers of its service provider
apps/
├── Iam/              owns the users
├── Analytics/        records signups, computes datasets, keeps a copy of the users
└── Notifications/    a personal inbox, fed by events, keeps a copy of the users
foundation/
├── FoundationServiceProvider.php
├── Common/           base classes every module uses (see below)
└── Iam/              what iam shares with the other modules
```

### The `iam` module

| File | What it does |
|---|---|
| `app/Models/User.php` | The user, stored in the `iam_users` table. The `name` field is copied to the modules that keep a copy. |
| `app/Actions/RegisterUser.php` | Creates the user and emits `iam.user.registered`. |
| `app/Actions/IssueToken.php` | Issues the user's token: a JWT, or an opaque token with `AUTH_TOKEN_VALIDATION_STRATEGY=issuer`. |
| `app/Http/Controllers/IdentityController.php` | `GET /iam/api/v1/identity`, which the gateway asks: a valid token gets the `X-Identity` the modules trust. |
| `app/Http/Controllers/AuthController.php` | `POST /iam/api/v1/register`, `login`, `refresh`, `logout`, `logout-all`, and `GET /iam/api/v1/me`. |
| `app/Services/IamService.php` | Answers the `IamService` contract with iam's own data. Declared in `IamServiceProvider::$services`, it serves every caller, in this process or another. |
| `config/database.php` | The `iam` connection, and the `owner` role that migrates it. |
| `config/auth.php` | Names `User` as the authenticated user of iam's routes. |

### The foundation

`foundation/Iam/` holds what the other modules are allowed to use from `iam`:

| File | What it does |
|---|---|
| `Contracts/IamService.php` | The contract: `findUser()` and `findUserByToken()`. |
| `Services/IamRpcService.php` | Every module calls `iam` through it: in this process when `iam` runs here, over HTTP otherwise. |
| `Auth/TrustedIdentity.php` | Reads and signs `X-Identity`, the only thing a module authenticates (see [Authentication](#authentication)). |
| `Auth/JwtTokens.php` | Checks a JWT iam issued, for iam's identity route. |
| `Auth/RpcPrincipals.php` | The request's user as iam answers `findUser()`, for a module that keeps no copy. |
| `Events/IamEvent.php`, `Events/UserRegisteredPayload.php` | The names of the events iam publishes, and the typed payload of each: iam builds it, a consumer reads it with `UserRegisteredPayload::from($payload)`. The payload class also declares its versions (`version()`, `upcast()`), so a handler only ever receives the current shape. |
| `Shadows/UserShadow.php` | The copy of iam's users, used as it is by every module that lists it in `$shadows`, and their authenticated user (`auth.principal`). |
| `database/shadows/` | The migration that creates the copy's table in the module that keeps it. |

`foundation/FoundationServiceProvider.php` maps `IamService` to `IamRpcService`. The package then
runs iam's implementation in iam's context, so `Apps\Iam\Services\IamService` queries iam's
database whichever module calls it.

### The `analytics` module

| File | What it does |
|---|---|
| `app/Handlers/RecordSignup.php` | Handles `iam.user.registered` and stores a signup. |
| `app/Providers/AnalyticsServiceProvider.php` | `$shadows = [UserShadow::class]`: analytics keeps iam's users in its own `iam_users` table. |
| `app/Http/Controllers/SignupController.php` | `GET /analytics/api/v1/signups` lists the signups. `GET /analytics/api/v1/users/{id}` asks `iam` through the contract. |
| `app/Http/Resources/SignupResource.php` | Adds the user to each signup with `UserResource`, read from the copy: no call to `iam` per row. |
| `app/Rules/ExistingUser.php` | A `ReferenceRule` that asks `iam` through the contract. `?user_id=` on the signups list uses it. |
| `app/Console/ComputeDatasets.php` | `analytics:compute` rebuilds `analytics_datasets`, one row per hour, from the signups. `AnalyticsServiceProvider` schedules it hourly. |
| `app/Enums/DatasetMeasure.php`, `MeasureNature.php` | The measures, and how a period folds them: a flow (`signups_count`) adds up, a state (`users_total`) keeps the last value. |
| `app/Enums/DatasetGroup.php`, `app/Queries/ReadDatasets.php` | `GET /analytics/api/v1/datasets?group=hour\|day\|week\|month\|none&measures[]=…&from=…&to=…` folds the hourly rows onto the group. |
| `app/Providers/AnalyticsServiceProvider.php` | Declares the handler in `$handlers` and schedules `analytics:compute`. |

A reading never walks the signups: it filters and folds the pre-computed rows, so its cost
depends on the period, not on the volume.

A module reads another module's data in two ways, and the skeleton shows both:

| | Through the contract (`ExistingUser`) | From the copy (`SignupResource`) |
|---|---|---|
| Cost | one call per value, an HTTP call when `iam` runs elsewhere | a local query, joins included |
| Freshness | always current | current once the consumer has handled the events |
| Use it for | checking one value, reading one record | lists, filters, sorts |

### The `notifications` module

| File | What it does |
|---|---|
| `app/Handlers/SendWelcome.php` | Handles `iam.user.registered` and stores a welcome for the new user, once however many times the event arrives. |
| `app/Models/Notification.php` | What one person was told, in `notifications_inbox`. It is addressed to its recipient, so every query scopes on the authenticated user: there is no policy to write. |
| `app/Enums/NotificationType.php` | What a notification is about; it renders the title from `lang/en/messages.php` and the recipient's copy. |
| `app/Http/Controllers/NotificationController.php` | `GET /notifications/api/v1/notifications?filter[unread]=1&sort=-created_at&paginate=20` and `PATCH /notifications/api/v1/notifications/{id}/read`. Both scope on `principalIdOrFail()`: someone else's notification is a 404. |
| `app/Repositories/NotificationRepository.php` | The example of `EloquentRepository`: it declares the filters and sorts a request may use (any other is a 400), and the controller passes the recipient scope as `$constrain`. |
| `app/Providers/NotificationsServiceProvider.php` | `$shadows = [UserShadow::class]`: its copy of iam's users, in `iam_users`, the authenticated user of its routes. |
| `app/Observers/NotificationObserver.php` | Once the row is committed: pushes it live, then queues one job per channel its type names. |
| `app/Events/NotificationPushed.php` | The live push, on `private-user.{id}` (Reverb), in the shape the inbox returns; a client that was offline finds it in the inbox. |
| `app/Enums/Channel.php`, `app/Jobs/SendMail.php` | The channels beyond the inbox. `SendMail` reads the address in the module's copy of iam's users (`UserShadow`), so a mail leaves even when iam is down. A new channel (SMS, push) is a case and a job. |
| `routes/api.php` | Also `POST /notifications/api/v1/broadcasting/auth`: Echo joins `private-user.{id}` with the API token; only that user may join (`Foundation\Common\Broadcasting\PrivateUserChannel`). |

```
iam ─ user.registered ─► consumer ─► SendWelcome ─► notifications_inbox
                                                      ├─► NotificationPushed ─► Reverb :8080 ─► browser
                                                      └─► SendMail (queue) ─► worker ─► mail
```

### `foundation/Common`

`Foundation\Common\` holds the base classes every module uses. Like the rest of the foundation,
any module can use it. These classes are yours: the package doesn't depend on them, so you can
change or delete them.

How strongly each piece is meant is labelled:

| Label | Meaning |
|---|---|
| Mechanism | the modules rely on it working this way across processes; replace it only with something that keeps the same guarantee |
| Default | a sensible choice that works as shipped; swap it freely |
| Proposal | one way to structure the code, shown so you can judge it |
| Taste | a style choice, nothing depends on it |

| Path | What it does | Label |
|---|---|---|
| `Http/Controller`, `ApiRequest`, `Resource`, `ApiErrorCode` | Base controller and request, and the response format: `{success, data}` or `{success, code, message}`. | Taste |
| `Http/Middleware/Authenticate`, `Auth/TokenValidator`, `PrincipalResolver`, `PermissionSource`, `Identity`, `Principal` | Token authentication for module routes, in three replaceable steps: see [Authentication](#authentication). | Mechanism |
| `Auth/LocalPrincipals`, `ClaimsPrincipals`, `ClaimsPermissions`, `IsPrincipal`, `HasPrincipal` | The shipped answers to those steps, and the traits a user model and a controller use. | Default |
| `Policies/Policy` | Checks a permission against the `PermissionSource`. | Mechanism |
| `Contracts/Action`, `Operation` | One class per use case. | Proposal |
| `Contracts/Repository`, `Database/EloquentRepository`, `Searchable` | Queries with filters, sorts and includes, and a single place for writes. | Proposal |
| `Data/Dto` | Typed input and event payloads, built on spatie/laravel-data. | Default |
| `Validation/ReferenceRule` | A field holding the id of another module's record: checks it exists through that module's contract, then runs the constraints a subclass adds to `$checks`. | Mechanism |
| `Exceptions/DomainException`, `IntegrityException` | Business errors (400) and system errors (500). | Taste |

`bootstrap/app.php` renders the exceptions that implement `RendersApiEnvelope` in the response
format above.

## Authentication

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

| Interface | Config key | Shipped | To add your own |
|---|---|---|---|
| `PrincipalResolver` | `auth.principal_resolver` | `LocalPrincipals`: the user's row in the running module's own database, through `auth.principal`: `UserShadow` in the modules that keep it, `User` in iam, which sets it in its `config/auth.php`. `ClaimsPrincipals`: the user is built from the claims, no row. `RpcPrincipals`: the user iam returns for the id | name your class |
| `PermissionSource` | `auth.permission_source` | `IamPermissions`: `IamService::grants()`. `ClaimsPermissions`: the `permissions` claim | name your class |

A request is refused (401, Laravel's `AuthenticationException`) when its `X-Identity` is missing,
expired or not signed with the secret. With `LocalPrincipals` it is also refused while the module
has no copy of the user: a user who has just registered reaches `analytics` once it has consumed
`iam.user.registered`.

In a controller that extends `Foundation\Common\Http\Controller`, `$this->principalId()` returns the id of
the authenticated user.

### How the gateway validates a token

`AUTH_TOKEN_VALIDATION_STRATEGY` says how the gateway checks the client's token. iam reads it too,
to issue the token that strategy checks (`POST /iam/api/v1/login` and `/refresh`): both must read
the same value.

| | `signature` (default) | `issuer` |
|---|---|---|
| The token iam issues | a JWT, `sub` = the user id | a Sanctum personal access token; iam stores its hash, one per device |
| The gateway checks it | with iam's public key, then the cached user | by asking iam, on every request |
| Settings | the key pair, in `AUTH_JWT_KEYS_PATH` or `AUTH_JWT_*_KEY` | none |

`AUTH_TOKEN_TTL` is how long a token lives, whichever the strategy. Under `issuer`, a logout
(`POST /iam/api/v1/logout`, or `/logout-all` for every device) stops the token at once, so it
can be long. Under `signature`, the gateway never asks iam: a logout applies only when the JWT
expires, so `AUTH_TOKEN_TTL` is also the longest it takes. Keep it short then, and set
`AUTH_REFRESH_TOKEN_TTL` so the client renews it without asking for the password again: the login
also answers a `refresh_token`, spent once by `POST /iam/api/v1/refresh`.

`GET /iam/api/v1/identity` is what the gateway asks: for a valid token of either kind, it answers
204 with the `X-Identity` header, 401 otherwise. It reads the user through
`IamRpcService::findUser()`, so it also fills the cache the gateway reads next time.

### The gateway

`gateway/` holds the starter's gateway: Traefik, its configuration and its plugin. Both compose
files run it in front of the modules: see [`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-modules-starter/blob/main/gateway/README.md).

```
client ─► Traefik ─► auth plugin (gateway/plugins/auth)
                       signature  1. the JWT, checked with iam's public key
                                  2. GET database:cache:iam:user:{id}, in Redis database 1
                                       present → its claims signed here, iam is not called
                                       missing → GET iam /identity, which fills the key
                       issuer     GET iam /identity, every time
                     ─► the module, with X-Identity
iam saves or deletes a user ─► forgetUser() ─► the key is gone, the next request asks iam
```

A gateway of your own, in any language, does the same: it forwards `POST /iam/api/v1/register`,
`/login` and `/refresh` as they are, and adds `X-Identity` to every other request. It must also:

- drop any `X-Identity` the client sent;
- never forward `/*/rpc/*`: those routes accept any caller holding the RPC secret;
- be the only way in: anyone who reaches a module with a valid `X-Identity` is that user.

To let the gateway emit events or call the modules itself, see "Services in other languages" in
the package's README.

### An identity provider outside the application

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

| The provider's claim | What the modules read |
|---|---|
| `sub` | the user's id, an integer or a string (`usr_7f3a`) |
| `permissions` (`auth.permissions_claim`) | the list the policies check, such as `analytics.datasets.read` |
| any other claim | the user is a `ClaimsPrincipal`: `$principal->claim('email')` |

`apps/Analytics/tests/Feature/ExternalIdentityTest.php` runs it. To keep the users' names locally
all the same, the provider can feed the modules' copies: see "Being the source of a copy" in the
package's `docs/other-languages.md`.

### Without authentication

The package has no authentication of its own: all of it lives in this skeleton, in
`foundation/Common/Auth`, `foundation/Common/Http/Middleware/Authenticate.php`,
`foundation/Common/Policies` and `foundation/Iam/Auth`. These use them:

| File | What to remove |
|---|---|
| `foundation/FoundationServiceProvider.php` | the three bindings of `register()` |
| `apps/*/routes/api.php` | the `Authenticate` middleware |
| `foundation/Common/Http/Controller.php`, `ApiRequest.php` | `use HasPrincipal` |
| `apps/Iam/app/Models/User.php`, `foundation/Iam/Shadows/UserShadow.php` | `IsPrincipal` and `implements Principal` |
| `apps/Analytics/app/Policies/DatasetPolicy.php` | the policy, and `authorize()` in `DatasetController` |
| `tests/ModuleTestCase.php`, `apps/*/tests/Feature`, `tests/Integration` | the tests that send a token |

## Permissions

Roles and permissions belong to `iam`, which keeps them with `spatie/laravel-permission` in its
`iam_*` tables. Every other module asks for them through `IamService::grants($userId)`. That call
goes through RPC and the answer is cached.

- A module declares its permissions as an enum in the foundation, for example
  `Foundation\Analytics\Enums\AnalyticsPermission`, and lists it in `auth.permissions`.
  `php artisan iam:sync-permissions` then creates the declared permissions and drops every
  cached grant. With `--prune` it also deletes the permissions no enum declares, and every role's
  and user's hold on them: an enum missing from the list loses its grants. `composer setup`,
  `create-project` and the container whose `SYNC_PERMISSIONS` is `true` (iam's) run it with `--prune`.
- A policy extends `Foundation\Common\Policies\Policy` and checks
  `$this->allows($user, AnalyticsPermission::ReadDatasets)`. A controller calls
  `$this->authorize('viewAny', Dataset::class)`, which answers 403 when the user lacks the permission.
- Change permissions only through iam's actions, never by calling spatie directly, so the cache
  stays right. Each action clears the cache once its transaction commits:

  | Action | Clears |
  |---|---|
  | `GrantRole::grant()` / `revoke()` | that user's cached grants |
  | `SetRolePermissions::execute()` | every user's cached grants |

## Running a module in its own process

Every module runs in one process by default. To move `iam` to its own process, start two copies
of the same code with different settings:

```dotenv
# the iam process
RUN_MODULES=iam

# the analytics process
RUN_MODULES=analytics
IAM_HOST=http://iam.internal:8000
```

`config/distributable.php` declares every module in every process, so `analytics` still listens to
iam's events and calls iam over HTTP through `IamRpcService`. Both processes must share `APP_KEY`,
or the same `DISTRIBUTABLE_RPC_SECRET`.

When you build an image for one module, delete the folders of the others before
`composer dump-autoload`:

```bash
RUN_MODULES=analytics php artisan modules:purge --force
```

Starting that image with `RUN_MODULES=iam` then fails at boot, because iam's folder is gone.

## Docker

The skeleton ships both setups, on Postgres (one database per module) and Redis (the event
stream), served by Octane with Swoole:

```bash
# every module in one container
docker compose -f docker-compose.mono.yml up -d
# one container per module
docker compose up -d
```

In both, Traefik is the only way in, on `:8000` (`GATEWAY_PORT`); the modules' ports stay private.
Send the token iam issued: Traefik turns it into the `X-Identity` the modules read. The container
that runs iam writes its JWT key pair to the `jwt-keys` volume at its first start
(`WITH_JWT_KEYS`), and Traefik reads the public key there.

In the second setup, each image is built with `--build-arg RUN_MODULES=<module>`, so it holds only
its module: the Dockerfile runs `modules:purge` before `composer dump-autoload`. A container
migrates only the database of the module it runs, so two containers never migrate the same one.

| File | What it does |
|---|---|
| `docker/Dockerfile` | Installs the dependencies, purges the modules the image doesn't run and the packages only they required, then builds the PHP and Swoole runtime. |
| `docker/entrypoint.sh` | Writes iam's JWT keys (`WITH_JWT_KEYS`), runs `optimize`, migrates (on the `http` role only), then starts supervisord. |
| `gateway/` | Traefik in front of the modules, and its auth plugin: see [`gateway/README.md`](https://github.com/mk-josias/laravel-distributable-modules-starter/blob/main/gateway/README.md). |
| `docker/supervisord.conf` | The roles a container can take, each switched on by a variable. |
| `docker/postgres/` | A Postgres image that creates the databases of `MODULE_DATABASES`, owned by `distributable`, written by `distributable_app`. |

| Role | Variable | Default | Run at most |
|---|---|---|---|
| `http` (Octane) | `WITH_HTTP` | `true` | as many as you need |
| `worker` (`queue:work`) | `WITH_WORKER` | `false` | as many as you need |
| `reverb` (WebSocket, port 8080) | `WITH_REVERB` | `false` | one per container that pushes: it pushes to its own, on localhost |
| `scheduler` (supercronic) | `WITH_SCHEDULER` | `false` | one per module set: two would run each task twice |
| `consumers` (`stream:consume`) | `WITH_CONSUMERS` | `false` | one per module set: it serves every module of `RUN_MODULES`, each with its own cursor, and two would break the order a module reads in |

The values in the compose files are for development. In production, set real secrets.

## Tests

```bash
php artisan test
```

`tests/TestCase.php` gives each module an empty sqlite file and runs `migrate` before every
test.

A module is tested alone, the way it runs once it has its own process. Its tests live in
`apps/{Module}/tests/` and extend `Tests\ModuleTestCase`, which runs that module only
(`RUN_MODULES`) and stands in for iam:

```php
class InboxTest extends ModuleTestCase
{
    protected string $module = 'notifications';

    public function test_a_registered_user_finds_a_welcome(): void
    {
        // its copy, and a token
        $headers = $this->user(1, 'ada');
        // as iam would announce it
        $this->receive('notifications', IamEvent::UserRegistered->value, ['id' => 1]);

        $this->getJson('/notifications/api/v1/notifications', $headers)->assertJsonCount(1, 'data');
    }
}
```

| Helper | What it does |
|---|---|
| `user($id, $name, $permissions)` | writes the user's copy in the module's database, makes iam answer `$permissions` to `grants()`, and returns the `Authorization` header of a valid token |
| `receive($module, $name, $payload)` | hands the module an event as its emitter would have announced it (from the package) |

Because a module's tests name no other module's class, `Boundaries` holds for them too.

| Level | Where | Suite |
|---|---|---|
| unit | `tests/Unit`, `apps/{Module}/tests/Unit` | `Unit` |
| component | `apps/{Module}/tests/Feature` | `Modules` |
| integration | `tests/Integration` | `Integration` |
| end to end | `tests/e2e/together-or-apart.sh` | none |

- **Unit**: a class alone, with no database, HTTP or container.
- **Component**: one module alone, through its routes and the events it receives.
- **Integration**: every module in one process, through their routes. One file per subject: the
  databases stay apart, events, authentication and contracts cross modules. `ArchitectureTest`
  checks that no module uses another module's classes (`Distributable\Testing\Boundaries`) and that
  `modules:doctor` passes.
- **End to end**: the same user journey against the Docker setups, through Traefik.

`php artisan test --testsuite=Unit` runs the fast tests alone.

`php artisan make:test PreferenceTest --module=notifications` writes a test in the module.

A test reaches a module through its routes and contracts, not its tables. `inModuleOf` is for the
rare test with no route to take: it enters the module of the class, runs the callback, then leaves:

```php
$user = $this->inModuleOf(User::class, fn () => User::query()->create([...]));
```

`composer check` runs Pint, PHPStan and the tests.

## Adding code to a module

Laravel's `make:*` commands take `--module`:

```bash
# a new module, declared in config/distributable.php
php artisan modules:make <module> --database
# apps/Notifications/app/Models, its migration and its factory
php artisan make:model Preference -mf --module=notifications
php artisan make:controller PreferenceController --module=notifications
```

The root `composer.json` lists the foundation under `autoload.psr-4`, and each module's own
`composer.json` its classes. The application doesn't need those entries: the package autoloads the
modules itself. They let your IDE resolve the classes, through the merge described below; a new
module needs its own, as in `apps/Iam/composer.json`.

A library only one module uses goes in that module's `composer.json`, as `spatie/laravel-permission`
does in `apps/Iam/composer.json`. The root `composer.json` merges `apps/*/composer.json`
(`wikimedia/composer-merge-plugin`), so there is still one `composer.lock` and one `vendor/`:

```bash
# declares it in the module
composer require league/csv --working-dir=apps/Analytics --no-update
# locks and installs it
composer update league/csv
```

What every module uses (`foundation/`) stays in the root file. An image built for one module leaves
out the packages only the other modules declared: the analytics image has no
`spatie/laravel-permission`.

## License

MIT.
