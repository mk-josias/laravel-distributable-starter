<?php

namespace Apps\Iam\Tests\Feature;

use Apps\Iam\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Foundation\Iam\Auth\TrustedIdentity;
use Illuminate\Support\Facades\DB;
use Tests\ModuleTestCase;

class AuthTest extends ModuleTestCase
{
    protected string $module = 'iam';

    protected function setUp(): void
    {
        parent::setUp();

        $this->register()->assertCreated();
    }

    public function test_the_right_password_issues_a_token_the_gateway_turns_into_an_identity(): void
    {
        $this->getJson('/iam/api/v1/me', $this->throughGateway($this->login()))->assertOk();
    }

    public function test_a_module_never_reads_the_client_token_itself(): void
    {
        $this->getJson('/iam/api/v1/me', ['Authorization' => "Bearer {$this->login()}"])->assertUnauthorized();
    }

    public function test_a_wrong_password_and_an_unknown_email_get_the_same_401(): void
    {
        $wrongPassword = $this->attemptLogin(password: 'wrong-horse');
        $unknownEmail = $this->attemptLogin(email: 'nobody@example.com');

        $wrongPassword->assertUnauthorized();
        $unknownEmail->assertUnauthorized();
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_the_password_is_stored_hashed_and_never_returned(): void
    {
        $stored = (string) DB::connection('iam')->table('iam_users')->value('password');

        $this->assertNotSame('correct-horse', $stored);
        $this->assertTrue(password_verify('correct-horse', $stored));
        $this->attemptLogin()->assertJsonMissingPath('data.password');
    }

    public function test_a_sixth_failed_login_for_an_email_from_one_address_is_throttled_and_another_email_is_not(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->attemptLogin(password: 'wrong-horse')->assertUnauthorized();
        }

        $this->attemptLogin(password: 'wrong-horse')->assertTooManyRequests();
        $this->attemptLogin()->assertTooManyRequests();
        $this->attemptLogin(email: 'nobody@example.com')->assertUnauthorized();
    }

    public function test_a_successful_login_clears_the_failures_of_its_email(): void
    {
        foreach (range(1, 4) as $attempt) {
            $this->attemptLogin(password: 'wrong-horse')->assertUnauthorized();
        }

        $this->login();

        $this->attemptLogin(password: 'wrong-horse')->assertUnauthorized();
    }

    public function test_iam_issues_a_jwt_the_gateway_verifies_with_its_public_key(): void
    {
        $token = $this->login();
        [$header, $payload] = explode('.', $token);

        $claims = JWT::decode($token, new Key((string) config('auth.tokens.jwt.public_key'), 'RS256'));

        $this->assertSame('RS256', json_decode(base64_decode($header), true)['alg']);
        $this->assertSame((string) User::query()->value('id'), $claims->sub);
        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$header}.{$payload}.forged"])->assertUnauthorized();
    }

    public function test_under_the_issuer_strategy_a_logout_stops_the_token_at_once(): void
    {
        config()->set('auth.tokens.validation', 'issuer');
        $token = $this->login();

        $this->assertSame(0, substr_count($token, '.'));
        $this->getJson('/iam/api/v1/me', $this->throughGateway($token))->assertOk()->assertJsonPath('data.id', 1);

        $this->postJson('/iam/api/v1/logout', [], $this->throughGateway($token))->assertNoContent();

        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_registering_gives_no_token_logging_in_does(): void
    {
        $this->register(name: 'Grace', email: 'grace@example.com')->assertCreated()->assertJsonMissingPath('data.token');

        $this->assertNotSame('', $this->login('grace@example.com'));
    }

    public function test_a_new_login_on_a_device_replaces_its_session_and_leaves_the_other_devices_theirs(): void
    {
        config()->set('auth.tokens.validation', 'issuer');

        $first = $this->login(device: 'phone');
        $second = $this->login(device: 'phone');
        $laptop = $this->login(device: 'laptop');

        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$first}"])->assertUnauthorized();
        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$second}"])->assertNoContent();
        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$laptop}"])->assertNoContent();
    }

    public function test_logging_out_of_every_device_ends_every_session(): void
    {
        config()->set('auth.tokens.validation', 'issuer');
        $phone = $this->login(device: 'phone');
        $laptop = $this->login(device: 'laptop');

        $this->postJson('/iam/api/v1/logout-all', [], $this->throughGateway($phone))->assertNoContent();

        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$phone}"])->assertUnauthorized();
        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$laptop}"])->assertUnauthorized();
    }

    public function test_an_expired_token_opens_nothing(): void
    {
        config()->set('auth.tokens.validation', 'issuer');
        $token = $this->login();

        $this->travel((int) config('auth.tokens.ttl') + 1)->seconds();

        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
    }

    public function test_a_refresh_token_gives_a_new_session_once_and_never_opens_a_request(): void
    {
        config()->set('auth.tokens.refresh_ttl', 86400);
        $refreshToken = (string) $this->attemptLogin()->json('data.refresh_token');

        $this->postJson('/iam/api/v1/refresh', ['refresh_token' => $refreshToken])
            ->assertCreated()->assertJsonStructure(['data' => ['token', 'expires_at', 'refresh_token']]);

        $this->postJson('/iam/api/v1/refresh', ['refresh_token' => $refreshToken])->assertUnauthorized();
        $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$refreshToken}"])->assertUnauthorized();
    }

    public function test_a_module_trusts_only_an_identity_signed_with_the_shared_secret_and_not_expired(): void
    {
        $id = (int) User::query()->value('id');

        $this->getJson('/iam/api/v1/me', $this->identity($id))->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson('/iam/api/v1/me', ['X-Identity' => TrustedIdentity::sign(['sub' => $id], time() + 60, 'wrong')])->assertUnauthorized();
        $this->getJson('/iam/api/v1/me', ['X-Identity' => TrustedIdentity::sign(['sub' => $id], time() - 1, self::GATEWAY_SECRET)])->assertUnauthorized();
    }

    public function test_identity_signs_the_claims_of_the_token_for_the_modules(): void
    {
        $identity = (string) $this->get('/iam/api/v1/identity', ['Authorization' => "Bearer {$this->login()}"])->assertNoContent()->headers->get('X-Identity');
        $claims = (new TrustedIdentity(self::GATEWAY_SECRET))->validate($identity)?->claims;

        $this->assertSame('1', $claims['sub'] ?? null);
        $this->get('/iam/api/v1/identity', ['Authorization' => 'Bearer forged'])->assertUnauthorized();
        $this->get('/iam/api/v1/identity')->assertUnauthorized();
    }

    public function test_a_password_shorter_than_eight_characters_is_refused_at_registration(): void
    {
        $this->register(name: 'Grace', email: 'grace@example.com', password: 'short')
            ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }
}
