<?php

namespace Tests\Unit;

use App\Domain\Payments\Gateways\StripeGateway;
use App\Domain\Payments\InvalidWebhook;
use PHPUnit\Framework\TestCase;

class StripeSignatureTest extends TestCase
{
    public function test_signature_covers_the_exact_raw_body_and_supports_key_rotation(): void
    {
        $body = '{"id":"evt_fixture"}';
        $time = 1792886400;
        $signature = hash_hmac('sha256', $time.'.'.$body, 'fixture-secret');
        StripeGateway::verifySignature($body, 't='.$time.',v1='.str_repeat('0', 64).',v1='.$signature, 'fixture-secret', 300, $time);
        $this->expectException(InvalidWebhook::class);
        StripeGateway::verifySignature($body.' ', 't='.$time.',v1='.$signature, 'fixture-secret', 300, $time);
    }

    public function test_valid_old_signature_cannot_be_replayed_outside_tolerance(): void
    {
        $time = 1792886400;
        $signature = hash_hmac('sha256', $time.'.{}', 'fixture-secret');
        $this->expectException(InvalidWebhook::class);
        StripeGateway::verifySignature('{}', 't='.$time.',v1='.$signature, 'fixture-secret', 300, $time + 301);
    }
}
