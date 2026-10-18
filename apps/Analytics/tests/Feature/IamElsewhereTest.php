<?php

namespace Apps\Analytics\Tests\Feature;

use Distributable\RpcHttp\RpcSignature;
use Foundation\Iam\Auth\TrustedIdentity;
use Foundation\Iam\Contracts\IamService;
use Foundation\Iam\Services\IamRpcService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\ModuleTestCase;

/** iam runs elsewhere: analytics authenticates the gateway's X-Identity alone, and reads iam over signed HTTP. */
class IamElsewhereTest extends ModuleTestCase
{
    protected string $module = 'analytics';

    /** @var array{X-Identity: string} */
    private array $ada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user(1, 'Ada');

        Http::fake([
            'iam.test/iam/rpc/findUser' => fn (Request $request) => $request['arguments']['id'] === 1
                ? Http::response(['id' => 1, 'name' => 'Ada'])
                : Http::response(null, 404),
        ]);
    }

    public function test_the_contract_is_answered_by_the_rpc_service(): void
    {
        $this->assertInstanceOf(IamRpcService::class, $this->app->make(IamService::class));
        $this->getJson('/iam/api/v1/me')->assertNotFound();
    }

    public function test_a_route_of_analytics_authenticates_the_identity_the_gateway_signed_without_calling_iam(): void
    {
        $this->getJson('/analytics/api/v1/signups', $this->ada)->assertOk();
        $this->getJson('/analytics/api/v1/signups', ['X-Identity' => TrustedIdentity::sign(['sub' => 1], time() + 60, 'forged')])->assertUnauthorized();
        $this->getJson('/analytics/api/v1/signups', $this->identity(2))->assertUnauthorized();
        $this->getJson('/analytics/api/v1/signups', ['Authorization' => 'Bearer any-token'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_the_validation_rule_asks_iam_over_http(): void
    {
        $this->getJson('/analytics/api/v1/signups?user_id=1', $this->ada)->assertOk();

        $this->getJson('/analytics/api/v1/signups?user_id=999', $this->ada)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id' => 'No user has this id.']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://iam.test/iam/rpc/findUser'
            && $request->hasHeader(RpcSignature::SIGNATURE_HEADER));
    }
}
