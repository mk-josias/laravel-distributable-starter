<?php

namespace Apps\Analytics\Tests\Feature;

use Foundation\Common\Auth\ClaimsPrincipal;
use Foundation\Common\Auth\Identity;
use Foundation\Iam\Auth\RpcPrincipals;
use Illuminate\Support\Facades\Http;
use Tests\ModuleTestCase;

/** The user as iam describes it, with no copy in the module: asked by id. */
class IamIdentityTest extends ModuleTestCase
{
    protected string $module = 'analytics';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'iam.test/iam/rpc/1/find-user' => fn ($request) => Http::response($request['payload']['id'] === 5 ? ['id' => 5, 'name' => 'Ada'] : null),
        ]);
    }

    public function test_rpc_principals_ask_iam_for_the_user_of_an_identity(): void
    {
        $principal = $this->inModule('analytics', fn () => $this->app->make(RpcPrincipals::class)->resolve(new Identity(5)));

        $this->assertInstanceOf(ClaimsPrincipal::class, $principal);
        $this->assertSame('Ada', $principal->claim('name'));
        $this->assertNull($this->inModule('analytics', fn () => $this->app->make(RpcPrincipals::class)->resolve(new Identity(6))));
    }
}
