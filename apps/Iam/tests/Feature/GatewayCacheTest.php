<?php

namespace Apps\Iam\Tests\Feature;

use Apps\Iam\Repositories\UserRepository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\ModuleTestCase;

class GatewayCacheTest extends ModuleTestCase
{
    protected string $module = 'iam';

    public function test_a_registered_user_is_written_for_the_gateway(): void
    {
        $connection = $this->gatewayRedis();
        $connection->shouldReceive('setex')->once()->with('gateway:user:1', 3600, '{"id":1}');

        $this->register()->assertCreated();
    }

    public function test_a_deleted_user_is_removed_for_the_gateway(): void
    {
        $this->register()->assertCreated();
        $connection = $this->gatewayRedis();
        $connection->shouldReceive('del')->once()->with('gateway:user:1');

        app(UserRepository::class)->delete(1);
    }

    public function test_nothing_is_written_without_a_gateway_connection(): void
    {
        config()->set('auth.gateway.users_connection', null);
        Redis::shouldReceive('connection')->never();

        $this->register()->assertCreated();
    }

    /** @return MockInterface&Connection */
    private function gatewayRedis(): MockInterface
    {
        config()->set('auth.gateway.users_connection', 'gateway');
        /** @var MockInterface&Connection $connection */
        $connection = Mockery::mock(Connection::class);
        Redis::shouldReceive('connection')->with('gateway')->andReturn($connection);

        return $connection;
    }
}
