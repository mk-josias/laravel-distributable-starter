<?php

namespace Tests\Unit;

use Foundation\Iam\Auth\TrustedIdentity;
use PHPUnit\Framework\TestCase;

class TrustedIdentityTest extends TestCase
{
    public function test_it_reads_back_the_claims_it_signed_with_an_integer_subject(): void
    {
        $identity = (new TrustedIdentity('secret'))->validate(TrustedIdentity::sign(['sub' => '7', 'name' => 'Ada'], time() + 60, 'secret'));

        $this->assertSame(7, $identity?->id);
        $this->assertSame('Ada', $identity?->claims['name'] ?? null);
    }

    public function test_it_refuses_another_secret_an_expired_identity_and_a_tampered_payload(): void
    {
        $tokens = new TrustedIdentity('secret');
        [$payload, $hmac] = explode('.', TrustedIdentity::sign(['sub' => 7], time() + 60, 'secret'));

        $this->assertNull($tokens->validate(TrustedIdentity::sign(['sub' => 7], time() + 60, 'other')));
        $this->assertNull($tokens->validate(TrustedIdentity::sign(['sub' => 7], time() - 1, 'secret')));
        $this->assertNull($tokens->validate(strrev($payload).'.'.$hmac));
    }
}
