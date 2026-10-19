<?php

namespace Apps\Analytics\Tests\Feature;

use Distributable\RpcHttp\RpcSignature;
use Foundation\Analytics\Enums\AnalyticsPermission;
use Foundation\Iam\Auth\TrustedIdentity;
use Foundation\Iam\Services\IamClient;
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

        $this->ada = $this->user(1, 'Ada', [AnalyticsPermission::ReadDatasets->value]);

        Http::fake([
            'iam.test/iam/rpc/1/find-user' => fn (Request $request) => $request['payload']['id'] === 1
                ? Http::response(['id' => 1, 'name' => 'Ada'])
                : Http::response(null, 404),
        ]);
    }

    public function test_iam_is_reached_over_http(): void
    {
        $this->assertSame(['id' => 1, 'name' => 'Ada'], $this->app->make(IamClient::class)->findUser(1)?->toArray());
        $this->getJson('/iam/api/v1/me')->assertNotFound();
    }

    public function test_a_route_of_analytics_authenticates_the_identity_the_gateway_signed_without_asking_iam_who_it_is(): void
    {
        $this->getJson('/analytics/api/v1/metrics', $this->ada)->assertOk();
        $this->getJson('/analytics/api/v1/metrics', ['X-Identity' => TrustedIdentity::sign(['sub' => 1], time() + 60, 'forged')])->assertUnauthorized();
        $this->getJson('/analytics/api/v1/metrics', $this->identity(2))->assertUnauthorized();
        $this->getJson('/analytics/api/v1/metrics', ['Authorization' => 'Bearer any-token'])->assertUnauthorized();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'find-user'));
    }

    public function test_the_permission_is_asked_to_iam_over_signed_http(): void
    {
        $this->getJson('/analytics/api/v1/metrics', $this->ada)->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://iam.test/iam/rpc/1/grants'
            && $request->hasHeader(RpcSignature::SIGNATURE_HEADER));
    }
}
