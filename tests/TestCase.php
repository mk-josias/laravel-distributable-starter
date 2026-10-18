<?php

namespace Tests;

use Distributable\Services\Modules\ModuleRegistry;
use Distributable\Testing\ModuleAware;
use Foundation\Iam\Auth\TrustedIdentity;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class TestCase extends BaseTestCase
{
    use ModuleAware;

    /** @var list<string> */
    private array $databases = [];

    /** @var array{public: string, private: string}|null */
    private static ?array $jwtKeys = null;

    /** Gives each module its own empty sqlite file and migrates everything. */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.tokens.jwt.public_key', self::jwtKeys()['public']);
        config()->set('auth.tokens.jwt.private_key', self::jwtKeys()['private']);
        config()->set('auth.gateway.secret', self::GATEWAY_SECRET);

        foreach ($this->app->make(ModuleRegistry::class)->local() as $module) {
            if (! $module->hasDatabase) {
                continue;
            }

            $this->databases[] = $file = (string) tempnam(sys_get_temp_dir(), "{$module->name}-");

            config()->set("database.connections.{$module->defaultConnection()}.database", $file);
            DB::purge($module->defaultConnection());
        }

        $this->artisan('migrate')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        array_map(unlink(...), $this->databases);
    }

    /** @return array{public: string, private: string} */
    private static function jwtKeys(): array
    {
        if (self::$jwtKeys === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $private);

            self::$jwtKeys = ['public' => openssl_pkey_get_details($key)['key'], 'private' => $private];
        }

        return self::$jwtKeys;
    }

    protected const string GATEWAY_SECRET = 'testing-gateway-secret';

    /**
     * The X-Identity a gateway sends after it checked a token of $sub carrying $claims.
     *
     * @param  array<string, mixed>  $claims
     * @return array{X-Identity: string}
     */
    protected function identity(int|string $sub, array $claims = []): array
    {
        return ['X-Identity' => TrustedIdentity::sign(['sub' => $sub, ...$claims], time() + 60, self::GATEWAY_SECRET)];
    }

    /** @return TestResponse<Response> */
    protected function register(string $name = 'Ada', string $email = 'ada@example.com', string $password = 'correct-horse'): TestResponse
    {
        return $this->postJson('/iam/api/v1/register', ['name' => $name, 'email' => $email, 'password' => $password]);
    }

    /**
     * POST login as a client does, from $device when given.
     *
     * @return TestResponse<Response>
     */
    protected function attemptLogin(string $password = 'correct-horse', string $email = 'ada@example.com', ?string $device = null): TestResponse
    {
        return $this->postJson('/iam/api/v1/login', ['email' => $email, 'password' => $password], $device === null ? [] : ['X-Device-Id' => $device]);
    }

    /** Logs a registered user in and returns the token. */
    protected function login(string $email = 'ada@example.com', ?string $device = null): string
    {
        return (string) $this->attemptLogin(email: $email, device: $device)->assertCreated()->json('data.token');
    }

    /**
     * What the gateway does with a client's token: asks iam's identity route, then sends its X-Identity on.
     *
     * @return array{X-Identity: string}
     */
    protected function throughGateway(string $token): array
    {
        return ['X-Identity' => (string) $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$token}"])->headers->get('X-Identity')];
    }
}
