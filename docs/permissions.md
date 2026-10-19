# Permissions

Roles and permissions belong to `iam`, which keeps them with `spatie/laravel-permission` in its
`iam_*` tables. Every other module asks for them through `IamClient::grants($userId)`. That call
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
