<?php

namespace Apps\Analytics\Tests\Feature;

use Foundation\Analytics\Enums\AnalyticsPermission;
use Foundation\Common\Auth\ClaimsPermissions;
use Foundation\Common\Auth\ClaimsPrincipals;
use Foundation\Iam\Auth\TrustedIdentity;
use Illuminate\Support\Facades\Http;
use Tests\ModuleTestCase;

/** The identity provider is outside the application: no iam, no copy of the user; the claims the gateway signs say it all. */
class ExternalIdentityTest extends ModuleTestCase
{
    protected string $module = 'analytics';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.principal_resolver', ClaimsPrincipals::class);
        config()->set('auth.permission_source', ClaimsPermissions::class);
    }

    public function test_the_claims_of_a_token_issued_elsewhere_authenticate_and_carry_the_permissions(): void
    {
        $this->getJson('/analytics/api/v1/metrics', $this->identity('usr_7f3a', ['permissions' => [AnalyticsPermission::ReadDatasets->value]]))->assertOk();
        $this->getJson('/analytics/api/v1/metrics', $this->identity('usr_7f3a', ['permissions' => []]))->assertForbidden();
        $this->getJson('/analytics/api/v1/metrics', ['X-Identity' => TrustedIdentity::sign(['sub' => 'usr_7f3a'], time() + 60, 'forged')])->assertUnauthorized();

        Http::assertNothingSent();
    }
}
