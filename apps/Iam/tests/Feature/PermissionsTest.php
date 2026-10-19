<?php

namespace Apps\Iam\Tests\Feature;

use Apps\Iam\Actions\GrantRole;
use Apps\Iam\Actions\SetRolePermissions;
use Apps\Iam\Models\User;
use Apps\Iam\Tests\Fixtures\SamplePermission;
use Foundation\Iam\Services\IamClient;
use Illuminate\Support\Facades\DB;
use Tests\ModuleTestCase;

class PermissionsTest extends ModuleTestCase
{
    protected string $module = 'iam';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.permissions', [SamplePermission::class]);
        $this->artisan('iam:sync-permissions')->expectsOutputToContain('1 permissions declared, 0 deleted.')->assertSuccessful();
        $this->register()->assertCreated();
        $this->user = User::query()->firstOrFail();
    }

    public function test_a_grant_and_a_revocation_are_answered_at_once_despite_the_cache(): void
    {
        $this->assertSame([], app(IamClient::class)->grants($this->user->id));

        app(SetRolePermissions::class)->execute('analyst', [SamplePermission::Read]);
        app(GrantRole::class)->grant($this->user, 'analyst');

        $this->assertSame(['sample.read'], app(IamClient::class)->grants($this->user->id));

        app(GrantRole::class)->revoke($this->user, 'analyst');

        $this->assertSame([], app(IamClient::class)->grants($this->user->id));
    }

    public function test_changing_a_role_drops_the_cached_grants_of_every_user_holding_it(): void
    {
        app(SetRolePermissions::class)->execute('analyst', [SamplePermission::Read]);
        app(GrantRole::class)->grant($this->user, 'analyst');

        $this->assertSame(['sample.read'], app(IamClient::class)->grants($this->user->id));

        app(SetRolePermissions::class)->execute('analyst', []);

        $this->assertSame([], app(IamClient::class)->grants($this->user->id));
    }

    public function test_only_prune_deletes_a_permission_no_module_declares(): void
    {
        DB::table('iam_permissions')->insert(['name' => 'gone.permission', 'guard_name' => 'web']);

        $this->artisan('iam:sync-permissions')->expectsOutputToContain('1 permissions declared, 0 deleted.')->assertSuccessful();
        $this->artisan('iam:sync-permissions --prune')->expectsOutputToContain('1 permissions declared, 1 deleted.')->assertSuccessful();
    }
}
