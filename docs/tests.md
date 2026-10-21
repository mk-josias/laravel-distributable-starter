# Tests

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

- **`user($id, $name, $permissions)`:** writes the user's copy in the module's database, makes iam
  answer `$permissions` to `grants()`, and returns the `Authorization` header of a valid token
- **`receive($module, $name, $payload)`:** hands the module an event as its emitter would have
  announced it (from the package)

Because a module's tests name no other module's class, `Boundaries` holds for them too.

- **unit**
  - Where: `tests/Unit`, `apps/{Module}/tests/Unit`
  - Suite: `Unit`
- **component**
  - Where: `apps/{Module}/tests/Feature`
  - Suite: `Modules`
- **integration**
  - Where: `tests/Integration`
  - Suite: `Integration`
- **end to end**
  - Where: `tests/e2e/together-or-apart.sh`
  - Suite: none

- **Unit**: a class alone, with no database, HTTP or container.
- **Component**: one module alone, through its routes and the events it receives.
- **Integration**: every module in one process, through their routes. One file per subject: the
  databases stay apart, events, authentication and RPC messages cross modules. `ArchitectureTest`
  checks that no module uses another module's classes (`Distributable\Testing\Boundaries`) and that
  `modules:doctor` passes.
- **End to end**: the same user journey against the Docker setups, through Traefik.

`php artisan test --testsuite=Unit` runs the fast tests alone.

`php artisan make:test PreferenceTest --module=notifications` writes a test in the module.

A test reaches a module through its routes and RPC messages, not its tables. `inModuleOf` is for the
rare test with no route to take: it enters the module of the class, runs the callback, then leaves:

```php
$user = $this->inModuleOf(User::class, fn () => User::query()->create([...]));
```

`composer check` runs Pint, PHPStan and the tests.
