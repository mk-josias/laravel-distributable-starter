<?php

namespace Tests\Integration;

use Foundation\Iam\Services\IamClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContractsAcrossModulesTest extends TestCase
{
    public function test_a_module_reads_another_module_through_its_client_in_this_process(): void
    {
        $id = $this->register()->json('data.id');
        Http::fake();

        $this->assertSame(['id' => $id, 'name' => 'Ada'], $this->app->make(IamClient::class)->findUser($id)?->toArray());
        Http::assertNothingSent();
    }
}
