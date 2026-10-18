<?php

namespace Tests\Integration;

use Foundation\Iam\Contracts\IamService;
use Foundation\Iam\Services\IamRpcService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractsAcrossModulesTest extends TestCase
{
    public function test_a_validation_rule_checks_a_value_against_another_module_through_its_contract(): void
    {
        $response = $this->register();
        $headers = $this->throughGateway($this->login());
        $this->artisan('stream:consume --module=analytics')->assertSuccessful();

        $this->getJson('/analytics/api/v1/signups?user_id='.$response->json('data.id'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.user.name', 'Ada');

        $this->getJson('/analytics/api/v1/signups?user_id=999', $headers)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id' => 'No user has this id.']);
    }

    public function test_a_module_reads_another_module_through_its_contract_in_this_process(): void
    {
        $id = $this->register()->json('data.id');
        $headers = $this->throughGateway($this->login());
        $this->artisan('stream:consume --module=analytics')->assertSuccessful();
        Http::fake();

        $this->getJson("/analytics/api/v1/users/{$id}", $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Ada');

        $this->assertInstanceOf(IamRpcService::class, $this->app->make(IamService::class));
        Http::assertNothingSent();
    }
}
