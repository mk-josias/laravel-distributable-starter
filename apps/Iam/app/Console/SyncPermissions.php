<?php

declare(strict_types=1);

namespace Apps\Iam\Console;

use BackedEnum;
use Foundation\Iam\Services\IamClient;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Creates the permissions of the enums listed in auth.permissions, and drops every cached grant. */
final class SyncPermissions extends Command
{
    protected $signature = 'iam:sync-permissions {--prune : delete the permissions no module declares, and every grant of them}';

    protected $description = 'Create the permissions listed in auth.permissions';

    public function handle(IamClient $iam, PermissionRegistrar $registrar): int
    {
        $declared = [];

        foreach ((array) config('auth.permissions') as $enum) {
            foreach ($enum::cases() as $case) {
                /** @var BackedEnum $case */
                $declared[] = (string) $case->value;
                Permission::findOrCreate((string) $case->value, 'web');
            }
        }

        // A query delete: Permission::delete() detaches its users through auth.providers.users.model, which names no model here;
        // the pivots cascade instead.
        $deleted = $this->option('prune') ? Permission::query()->whereNotIn('name', $declared)->delete() : 0;
        $registrar->forgetCachedPermissions();
        $iam->flushGrants();

        $this->components->info(count($declared).' permissions declared, '.$deleted.' deleted.');

        return self::SUCCESS;
    }
}
