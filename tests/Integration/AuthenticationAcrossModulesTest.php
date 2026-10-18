<?php

namespace Tests\Integration;

use Tests\TestCase;

class AuthenticationAcrossModulesTest extends TestCase
{
    public function test_a_route_of_another_module_authenticates_with_the_identity_the_gateway_got_for_a_token_of_iam(): void
    {
        $this->register()->assertCreated();
        $token = $this->login();
        $this->artisan('stream:consume --module=analytics')->assertSuccessful();

        $this->getJson('/analytics/api/v1/signups')->assertUnauthorized();
        $this->getJson('/analytics/api/v1/signups', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();

        $this->getJson('/analytics/api/v1/signups', $this->throughGateway($token))
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Ada');
    }

    public function test_a_valid_identity_is_refused_until_the_module_holds_a_copy_of_the_user(): void
    {
        $this->register()->assertCreated();
        $token = $this->login();

        $this->getJson('/iam/api/v1/me', $this->throughGateway($token))->assertOk();
        $this->getJson('/analytics/api/v1/signups', $this->throughGateway($token))->assertUnauthorized();
    }
}
