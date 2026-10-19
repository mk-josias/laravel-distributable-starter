<?php

namespace Apps\Iam\Tests\Feature;

use Foundation\Iam\Services\IamClient;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\ModuleTestCase;

class FindUserByTokenTest extends ModuleTestCase
{
    protected string $module = 'iam';

    protected function setUp(): void
    {
        parent::setUp();

        $this->register()->assertCreated();
    }

    public function test_a_jwt_opens_its_user(): void
    {
        $this->assertSame(1, $this->iam()->findUserByToken($this->login())?->id);
    }

    public function test_an_opaque_token_opens_its_user(): void
    {
        config()->set('auth.tokens.validation', 'issuer');

        $this->assertSame(1, $this->iam()->findUserByToken($this->login())?->id);
    }

    public function test_a_forged_jwt_opens_nothing(): void
    {
        [$header, $payload] = explode('.', $this->login());

        $this->assertNull($this->iam()->findUserByToken("{$header}.{$payload}.forged"));
    }

    public function test_a_refresh_token_never_opens_a_request(): void
    {
        config()->set('auth.tokens.refresh_ttl', 86400);
        $refreshToken = (string) $this->attemptLogin()->json('data.refresh_token');

        $this->assertNull($this->iam()->findUserByToken($refreshToken));
    }

    public function test_a_logout_stops_the_token_at_once(): void
    {
        config()->set('auth.tokens.validation', 'issuer');
        $token = $this->login();

        $this->postJson('/iam/api/v1/logout', [], $this->throughGateway($token))->assertNoContent();

        $this->assertNull($this->iam()->findUserByToken($token));
    }

    public function test_the_user_is_written_for_the_gateway(): void
    {
        $token = $this->login();
        config()->set('auth.gateway.users_connection', 'gateway');
        $connection = Mockery::mock(Connection::class);
        Redis::shouldReceive('connection')->with('gateway')->andReturn($connection);
        $connection->shouldReceive('setex')->once()->with('gateway:user:1', 3600, '{"id":1}');

        $this->iam()->findUserByToken($token);
    }

    private function iam(): IamClient
    {
        return app(IamClient::class);
    }
}
