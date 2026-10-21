# The starter's three modules

## The `iam` module

- `app/Models/User.php`: The user, stored in the `iam_users` table. The `name` field is copied to
  the modules that keep a copy.
- `app/Actions/RegisterUser.php`: Creates the user and emits `iam.user.registered`.
- `app/Actions/LoginUser.php`: Checks the email and the password, and issues the session: the same
  refusal for an unknown email and a wrong password.
- `app/Actions/IssueToken.php`: Issues the user's token: a JWT, or an opaque token with
  `AUTH_TOKEN_VALIDATION_STRATEGY=issuer`.
- `app/Dto/`: What each action receives, built by the request's `toDto()`.
- `app/Repositories/UserRepository.php`: The single write path of a user: it forgets the cached
  answers and rewrites the gateway's copy once the change is committed.
- `app/Http/Controllers/Api/AuthController.php`: `POST /iam/api/v1/register`, `login`, `refresh`,
  `logout`, `logout-all`, and `GET /iam/api/v1/me`.
- `app/Rpc/*Handler.php`: Answer the `FindUser`, `FindUserByToken` and `Grants` messages with iam's
  own data. Declared in `IamServiceProvider::$rpcHandlers`, they serve every caller, in this process
  or another.
- `config/database.php`: The `iam` connection, and the `owner` role that migrates it.
- `config/auth.php`: Names `User` as the authenticated user of iam's routes.

## The foundation

`foundation/Iam/` holds what the other modules are allowed to use from `iam`:

- `Rpc/FindUser.php`, `FindUserByToken.php`, `Grants.php`: The messages iam answers, with the rules
  that validate them.
- `Services/IamClient.php`: Every module calls `iam` through it: in this process when `iam` runs
  here, on the module's transport otherwise (HTTP with `rpc-http`).
- `Auth/TrustedIdentity.php`: Reads and signs `X-Identity`, the only thing a module authenticates
  (see [Authentication](./authentication.md)).
- `Auth/JwtTokens.php`: Checks a JWT iam issued, for the handler of `FindUserByToken`.
- `Auth/RpcPrincipals.php`: The request's user as iam answers `findUser()`, for a module that keeps
  no copy.
- `Events/IamEvent.php`, `Events/UserRegisteredPayload.php`: The names of the events iam publishes,
  and the typed payload of each: iam builds it, a consumer reads it with
  `UserRegisteredPayload::from($payload)`. The payload class also declares its version
  (`#[Payload]`) and how to lift an older one (`upcast()`), so a handler only ever receives the
  current shape.
- `Shadows/UserShadow.php`: The copy of iam's users, used as it is by every module that lists it in
  `$shadows`, and their authenticated user (`auth.principal`).
- `database/shadows/`: The migration that creates the copy's table in the module that keeps it.

The package runs each handler in iam's context, so `Apps\Iam\Rpc\FindUserHandler` queries iam's
database whichever module calls it.

## The `analytics` module

- `app/Handlers/RecordSignup.php`: Handles `iam.user.registered` and stores a signup.
- `app/Providers/AnalyticsServiceProvider.php`: `$shadows = [UserShadow::class]`: analytics keeps
  iam's users in its own `iam_users` table.
- `app/Console/ComputeDatasets.php`: `analytics:compute` rebuilds `analytics_datasets`, one row per
  hour, from the signups. `AnalyticsServiceProvider` schedules it hourly.
- `app/Enums/DatasetMeasure.php`, `MeasureNature.php`: The measures, and how a period folds them: a
  flow (`signups_count`) adds up, a state (`users_total`) keeps the last value.
- `app/Http/Controllers/Api/MetricController.php`, `app/Repositories/DatasetRepository.php`: `GET
  /analytics/api/v1/metrics?group=hour\|day\|week\|month\|none&measures[]=…&filter[from]=…&filter[to]=…`
  folds the hourly rows onto the group; `measure()` folds one measure over a period.
- `app/Enums/DatasetGroup.php`, `app/Dto/`, `app/Database/MonthlyPartitions.php`: The groupings,
  what the reading receives (built by the request's `toDto()`), and the monthly partitions of the
  datasets table.
- `app/Providers/AnalyticsServiceProvider.php`: Declares the handler in `$streamHandlers` and
  schedules `analytics:compute`.

A reading never walks the signups: it filters and folds the pre-computed rows, so its cost
depends on the period, not on the volume.

## The `notifications` module

- `app/Handlers/SendWelcome.php`: Handles `iam.user.registered` and stores a welcome for the new
  user, once however many times the event arrives.
- `app/Models/Notification.php`: What one person was told, in `notifications_inbox`. It is addressed
  to its recipient, so every query scopes on the authenticated user: there is no policy to write.
- `app/Enums/NotificationType.php`: What a notification is about; it renders the title from
  `lang/en/messages.php` and the recipient's copy.
- `app/Http/Controllers/Api/NotificationController.php`: `GET
  /notifications/api/v1/notifications?filter[unread]=1&sort=-created_at&paginate=20` and `PATCH
  /notifications/api/v1/notifications/{id}/read`. Both scope on the recipient: someone else's
  notification is a 404.
- `app/Actions/MarkNotificationRead.php`: Marks one notification of the recipient as read.
- `app/Dto/`, `app/Http/Requests/`: What the inbox and the action receive, built by the request's
  `toDto()`.
- `app/Repositories/NotificationRepository.php`: The example of `EloquentRepository`: it declares
  the filters and sorts a request may use (any other is a 400), scopes the inbox to its recipient,
  and is the one place a notification is marked read.
- `app/Providers/NotificationsServiceProvider.php`: `$shadows = [UserShadow::class]`: its copy of
  iam's users, in `iam_users`, the authenticated user of its routes.
- `app/Observers/NotificationObserver.php`: Once the row is committed: pushes it live, then queues
  one job per channel its type names.
- `app/Events/NotificationPushed.php`: The live push, on `private-user.{id}` (Reverb), in the shape
  the inbox returns; a client that was offline finds it in the inbox.
- `app/Enums/Channel.php`, `app/Jobs/SendMail.php`: The channels beyond the inbox. `SendMail` reads
  the address in the module's copy of iam's users (`UserShadow`), so a mail leaves even when iam is
  down. A new channel (SMS, push) is a case and a job.
- `routes/api.php`: Also `POST /notifications/api/v1/broadcasting/auth`: Echo joins
  `private-user.{id}` with the API token; only that user may join
  (`Foundation\Common\Broadcasting\PrivateUserChannel`).

```
iam ─ user.registered ─► consumer ─► SendWelcome ─► notifications_inbox
                                                  ├─► NotificationPushed ─► Reverb :8080 ─► browser
                                                  └─► SendMail (queue) ─► worker ─► mail
```

## `foundation/Common`

`Foundation\Common\` holds the base classes every module uses. Like the rest of the foundation,
any module can use it. These classes are yours: the package doesn't depend on them, so you can
change or delete them.

Each item below ends with a label that says what you can do with it:

- **Mechanism:** the modules rely on it working this way across processes. Replace it only with
  something that keeps the same guarantee.
- **Default:** a sensible choice that works as shipped. Swap it freely.
- **Proposal:** one way to structure the code, shown so you can judge it.
- **Taste:** a style choice. Nothing depends on it.

- `Http/Controllers/Controller`, `Http/Requests/ApiRequest`, `Http/Resources/Resource`,
  `Enums/ApiErrorCode`: Base controller and request, and the response format: `{success, data}` or
  `{success, code, message}`. *(Taste)*
- `Http/Middleware/Authenticate`, `Contracts/Auth/TokenValidator`, `PrincipalResolver`,
  `PermissionSource`, `Principal`, `Auth/Identity`: Token authentication for module routes, in three
  replaceable steps: see [Authentication](./authentication.md). *(Mechanism)*
- `Auth/LocalPrincipals`, `ClaimsPrincipals`, `ClaimsPermissions`, `Traits/IsPrincipal`,
  `HasPrincipal`: The shipped answers to those steps, and the traits a user model and a controller
  use. *(Default)*
- `Policies/Policy`: Checks a permission against the `PermissionSource`. *(Mechanism)*
- `Contracts/Actions/Action`, `Operation`: One class per use case. *(Proposal)*
- `Contracts/Database/Repository`, `Repositories/EloquentRepository`, `Traits/Searchable`: Queries
  with filters, sorts and includes, and a single place for writes. *(Proposal)*
- `Dto/Dto`: Typed input and event payloads, built on spatie/laravel-data. *(Default)*
- `Validation/ReferenceRule`: A field holding the id of another module's record: checks it exists
  through that module's client, then runs the constraints a subclass adds to `$checks`.
  *(Mechanism)*
- `Exceptions/DomainException`, `IntegrityException`: Business errors (400) and system errors (500).
  *(Taste)*

`bootstrap/app.php` renders the exceptions that implement `RendersApiEnvelope` in the response
format above.
