<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/** Run once by create-project: removes the example modules you don't keep, and sets the tokens iam issues. */
final class SetupSkeleton extends Command
{
    protected $signature = 'skeleton:setup
        {--remove=* : An example module to remove: analytics, notifications}
        {--strategy= : signature or issuer}';

    protected $description = 'Choose the example modules to keep and how the gateway validates a token';

    /**
     * iam stays: the other modules, authentication and permissions rely on it.
     *
     * @var array<string, array{tests: list<string>, lines: array<string, list<string>>}>
     */
    private const array REMOVABLE = [
        'analytics' => [
            'tests' => ['DatabaseIsolationTest', 'EventsAcrossModulesTest', 'AuthenticationAcrossModulesTest', 'ContractsAcrossModulesTest'],
            'lines' => [
                'config/auth.php' => [
                    "use Foundation\\Analytics\\Enums\\AnalyticsPermission;\n",
                    "        AnalyticsPermission::class,\n",
                ],
                'gateway/traefik/dynamic/routers.yml' => [
                    "    analytics:\n      rule: PathPrefix(`/analytics/api`)\n      middlewares: [auth]\n      service: analytics\n",
                    "    analytics:\n      loadBalancer:\n        servers: [{ url: \"http://analytics:8000\" }]\n",
                ],
            ],
        ],
        'notifications' => [
            'tests' => [],
            'lines' => [
                'gateway/traefik/dynamic/routers.yml' => [
                    "    notifications:\n      rule: PathPrefix(`/notifications/api`)\n      middlewares: [auth]\n      service: notifications\n",
                    "    notifications:\n      loadBalancer:\n        servers: [{ url: \"http://notifications:8000\" }]\n",
                ],
            ],
        ],
    ];

    public function handle(): int
    {
        $present = array_values(array_filter(array_keys(self::REMOVABLE), fn (string $module): bool => array_key_exists($module, (array) config('distributable.declared'))));

        /** @var list<string> $remove */
        $remove = $this->option('remove') !== [] ? (array) $this->option('remove') : ($present === [] ? [] : array_values(array_diff($present, multiselect(
            label: 'Which example modules do you keep? iam stays: the others rely on it.',
            options: $present,
            default: $present,
        ))));

        foreach (array_intersect($remove, $present) as $module) {
            $this->remove($module);
        }

        $strategy = (string) ($this->option('strategy') ?? select(
            label: 'How does the gateway validate a token? See the README, "Authentication".',
            options: ['signature' => 'signature: a JWT, checked with iam\'s public key', 'issuer' => 'issuer: an opaque token, iam is asked on every request'],
            default: 'signature',
        ));

        $this->setEnv('AUTH_TOKEN_VALIDATION_STRATEGY', $strategy);

        // The gateway signs X-Identity with it, the modules check it.
        if ((string) config('auth.gateway.secret') === '') {
            $this->setEnv('AUTH_GATEWAY_SECRET', Str::random(64));
        }

        $this->components->info("Token validation strategy: {$strategy}.");
        $this->removeItself();

        return self::SUCCESS;
    }

    /** Run again later, it would delete a module that has since become the application's own code. */
    private function removeItself(): void
    {
        $this->strip('composer.json', [
            "            \"@php artisan skeleton:setup --ansi\",\n",
            "            \"App\\\\\": \"app/\",\n",
        ]);
        $this->strip('phpstan.neon.dist', ["        - app\n"]);
        $this->strip('phpunit.xml', ["            <directory>app</directory>\n"]);
        $this->removeMonorepoPaths();
        @unlink(__FILE__);
        @rmdir(dirname(__FILE__));
        @rmdir(dirname(__FILE__, 2));
        @rmdir(dirname(__FILE__, 3));
    }

    /** The packages come from Packagist here: what reads them from the monorepo's folders goes. */
    private function removeMonorepoPaths(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        unset($composer['repositories']);
        file_put_contents(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        $this->strip('docker/Dockerfile', [
            "# The packages from a local path (see \"repositories\" in composer.json); empty outside the monorepo.\n",
            "COPY --from=packages . /packages\n\n",
            "COPY --from=vendor /packages /packages\n",
        ]);

        foreach (['docker-compose.yml', 'docker-compose.mono.yml'] as $file) {
            file_put_contents(base_path($file), (string) preg_replace(
                '/^(\s+)additional_contexts:\n\1  packages: .*\n/m',
                '',
                (string) file_get_contents(base_path($file)),
            ));
        }

        $this->strip('tests/e2e/together-or-apart.sh', [
            "# In the monorepo, the image takes the packages from their folders rather than from Packagist.\n"
            ."if [[ -d ../packages/core ]]; then\n"
            ."    export DISTRIBUTABLE_PACKAGES=../packages\n"
            ."fi\n\n",
        ]);

        @unlink(base_path('docker/no-packages/.gitkeep'));
        @rmdir(base_path('docker/no-packages'));
    }

    private function remove(string $module): void
    {
        $this->call('modules:delete', ['name' => $module, '--force' => true]);

        foreach (self::REMOVABLE[$module]['tests'] as $test) {
            @unlink(base_path("tests/Integration/{$test}.php"));
        }

        foreach (self::REMOVABLE[$module]['lines'] as $file => $lines) {
            $this->strip($file, $lines);
        }

        $this->removeFromCompose($module);
        $this->components->info("Example module [{$module}] removed.");
    }

    /** Drops the module's service and its names from the lists the containers read. */
    private function removeFromCompose(string $module): void
    {
        foreach (['docker-compose.yml', 'docker-compose.mono.yml'] as $file) {
            $contents = (string) file_get_contents(base_path($file));
            $contents = (string) preg_replace("/^  {$module}:\n(?:(?:    .*)?\n)*/m", '', $contents);
            $contents = (string) preg_replace("/^(\s+aliases: \[.*), {$module}(?=[,\]])/m", '$1', $contents);
            $contents = (string) preg_replace_callback(
                '/^(\s+MODULE_DATABASES: ")([^"]*)"/m',
                fn (array $match): string => $match[1].implode(str_contains($match[2], ',') ? ',' : ' ', array_values(array_diff(
                    (array) preg_split('/[ ,]/', $match[2]),
                    [$module, "app_{$module}"],
                ))).'"',
                $contents,
            );
            file_put_contents(base_path($file), $contents);
        }
    }

    /** @param list<string> $lines */
    private function strip(string $file, array $lines): void
    {
        file_put_contents(base_path($file), str_replace($lines, '', (string) file_get_contents(base_path($file))));
    }

    private function setEnv(string $key, string $value): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $line = "{$key}={$value}";

        $contents = preg_match("/^{$key}=.*$/m", $contents) === 1
            ? (string) preg_replace("/^{$key}=.*$/m", $line, $contents)
            : rtrim($contents, "\n")."\n{$line}\n";

        file_put_contents($path, $contents);
    }
}
