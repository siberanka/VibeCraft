<?php

namespace Tests\Feature;

use App\Domain\Bridge\BridgeSignature;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\CommandTemplate;
use App\Domain\Commerce\DeliveryQueue;
use App\Domain\Minecraft\AccountLinking;
use App\Models\Delivery;
use App\Models\GameIdentity;
use App\Models\GameServer;
use App\Models\LinkCode;
use Illuminate\Support\Str;
use Tests\TestCase;

class BridgeIntegrityTest extends TestCase
{
    private function server(): GameServer
    {
        return GameServer::forceCreate([
            'name' => 'Fixture server', 'slug' => 'fixture-'.Str::lower(Str::random(8)),
            'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)), 'bridge_secret' => 'fixture-only-hmac-key', 'bridge_enabled' => true,
        ]);
    }

    private function signedHeaders(GameServer $server, string $body, string $nonce, ?int $timestamp = null, string $path = '/api/bridge/v1/poll'): array
    {
        $timestamp ??= time();

        return ['CONTENT_TYPE' => 'application/json', 'HTTP_X_VC_KEY' => $server->bridge_key_id,
            'HTTP_X_VC_TIMESTAMP' => (string) $timestamp, 'HTTP_X_VC_NONCE' => $nonce,
            'HTTP_X_VC_SIGNATURE' => BridgeSignature::request($server->bridge_secret, 'POST', $path, $timestamp, $nonce, $body)];
    }

    public function test_bridge_request_nonce_is_single_use_and_response_is_signed(): void
    {
        $server = $this->server();
        $body = '{"online":[]}';
        $nonce = 'fixture_nonce_00000001';
        $headers = $this->signedHeaders($server, $body, $nonce);
        $response = $this->call('POST', '/api/bridge/v1/poll', [], [], [], $headers, $body)->assertOk();
        $expected = BridgeSignature::response($server->bridge_secret, (int) $response->headers->get('X-VC-Timestamp'), $nonce, $response->getContent());
        $response->assertHeader('X-VC-Signature', $expected);
        $this->call('POST', '/api/bridge/v1/poll', [], [], [], $headers, $body)->assertUnauthorized()->assertJsonPath('reason', 'replay');
    }

    public function test_body_tampering_and_expired_timestamp_are_rejected(): void
    {
        $server = $this->server();
        $headers = $this->signedHeaders($server, '{}', 'fixture_nonce_00000002');
        $this->call('POST', '/api/bridge/v1/poll', [], [], [], $headers, '{"players":1}')->assertUnauthorized();
        $headers = $this->signedHeaders($server, '{}', 'fixture_nonce_00000003', time() - 301);
        $this->call('POST', '/api/bridge/v1/poll', [], [], [], $headers, '{}')->assertUnauthorized()->assertJsonPath('reason', 'clock_skew');
    }

    public function test_expired_lease_reuses_delivery_id_and_ack_is_server_scoped(): void
    {
        $server = $this->server();
        $other = $this->server();
        $delivery = Delivery::create(['game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'say fixture',
            'dedup_key' => 'fixture:delivery', 'status' => Delivery::QUEUED, 'available_at' => now()->subSecond()]);
        $queue = app(DeliveryQueue::class);
        $first = $queue->lease($server, [], 25)->sole();
        $this->assertSame($delivery->public_id, $first->public_id);
        $this->assertCount(0, $queue->lease($server, [], 25));
        $this->travel(121)->seconds();
        $second = $queue->lease($server, [], 25)->sole();
        $this->assertSame($first->public_id, $second->public_id);
        $this->assertSame(2, $second->attempts);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first->lease_token);
        $this->assertNotSame($first->lease_token, $second->lease_token);
        $this->assertNull($queue->acknowledge($server, $first->public_id, $first->lease_token, 'delivered'));
        $this->assertSame(Delivery::LEASED, $delivery->fresh()->status);
        $this->assertNull($queue->acknowledge($other, $second->public_id, $second->lease_token, 'delivered'));
        $ack = $queue->acknowledge($server, $second->public_id, $second->lease_token, 'delivered');
        $duplicate = $queue->acknowledge($server, $second->public_id, $second->lease_token, 'failed', 'duplicate fixture');
        $this->assertSame(Delivery::DELIVERED, $duplicate->status);
        $this->assertTrue($ack->delivered_at->equalTo($duplicate->delivered_at));
    }

    public function test_manual_retry_invalidates_the_old_lease_receipt(): void
    {
        $server = $this->server();
        $delivery = Delivery::create([
            'game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'say fixture',
            'dedup_key' => 'fixture:retry-receipt', 'status' => Delivery::QUEUED, 'available_at' => now(),
        ]);
        $queue = app(DeliveryQueue::class);
        $first = $queue->lease($server, [], 25)->sole();
        $queue->retry($delivery->fresh());
        $this->assertNull($queue->acknowledge($server, $first->public_id, $first->lease_token, 'delivered'));
        $this->assertSame(Delivery::QUEUED, $delivery->fresh()->status);
        $second = $queue->lease($server, [], 25)->sole();
        $this->assertNotSame($first->lease_token, $second->lease_token);
        $this->assertSame(Delivery::DELIVERED, $queue->acknowledge($server, $second->public_id, $second->lease_token, 'delivered')->status);
    }

    public function test_duplicate_failed_receipt_does_not_reschedule_the_job(): void
    {
        $server = $this->server();
        Delivery::create([
            'game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'say fixture',
            'dedup_key' => 'fixture:duplicate-failure', 'status' => Delivery::QUEUED, 'available_at' => now(),
        ]);
        $queue = app(DeliveryQueue::class);
        $lease = $queue->lease($server, [], 25)->sole();
        $failed = $queue->acknowledge($server, $lease->public_id, $lease->lease_token, 'failed');
        $available = $failed->available_at->getTimestamp();
        $duplicate = $queue->acknowledge($server, $lease->public_id, $lease->lease_token, 'failed');
        $this->assertSame(Delivery::QUEUED, $duplicate->status);
        $this->assertSame(1, $duplicate->attempts);
        $this->assertSame($available, $duplicate->available_at->getTimestamp());
    }

    public function test_http_ack_requires_and_echoes_the_issued_lease_receipt(): void
    {
        $server = $this->server();
        $delivery = Delivery::create([
            'game_server_id' => $server->id, 'phase' => 'purchase', 'command' => 'say fixture',
            'dedup_key' => 'fixture:http-receipt', 'status' => Delivery::QUEUED, 'available_at' => now(),
        ]);
        $poll = '{"online":[]}';
        $response = $this->call('POST', '/api/bridge/v1/poll', [], [], [], $this->signedHeaders($server, $poll, 'fixture_poll_receipt_01'), $poll)->assertOk();
        $token = $response->json('deliveries.0.lease_token');
        $this->assertIsString($token);
        $this->assertArrayNotHasKey('lease_token', $delivery->fresh()->toArray());

        $result = ['id' => $delivery->public_id, 'status' => 'delivered'];
        $body = json_encode(['results' => [$result]], JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders($server, $body, 'fixture_ack_receipt_01', null, '/api/bridge/v1/ack');
        $this->call('POST', '/api/bridge/v1/ack', [], [], [], $headers, $body)->assertUnprocessable()->assertJsonValidationErrors(['results.0.lease_token']);

        $result['lease_token'] = ($token[0] === '0' ? '1' : '0').substr($token, 1);
        $body = json_encode(['results' => [$result]], JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders($server, $body, 'fixture_ack_receipt_02', null, '/api/bridge/v1/ack');
        $this->call('POST', '/api/bridge/v1/ack', [], [], [], $headers, $body)->assertOk()->assertJsonPath('accepted', []);
        $this->assertSame(Delivery::LEASED, $delivery->fresh()->status);

        $result['lease_token'] = $token;
        $body = json_encode(['results' => [$result]], JSON_THROW_ON_ERROR);
        $headers = $this->signedHeaders($server, $body, 'fixture_ack_receipt_03', null, '/api/bridge/v1/ack');
        $this->call('POST', '/api/bridge/v1/ack', [], [], [], $headers, $body)->assertOk()->assertJsonPath('accepted.0', $delivery->public_id);
        $this->assertSame(Delivery::DELIVERED, $delivery->fresh()->status);
    }

    public function test_link_code_is_hashed_single_use_and_identity_cannot_change_owner(): void
    {
        $server = $this->server();
        $first = $this->customer();
        $second = $this->customer();
        $linking = app(AccountLinking::class);
        $code = $linking->issueCode($first);
        $this->assertSame(hash('sha256', $code), LinkCode::sole()->code_hash);
        $identity = $linking->consume($server, $code, '12345678-1234-4234-8234-123456789abc', 'FixturePlayer');
        $this->assertSame($first->id, $identity->user_id);
        try {
            $linking->consume($server, $code, $identity->uuid, 'FixturePlayer');
            $this->fail('A consumed code must not be reusable.');
        } catch (CommerceException) {
            $this->assertSame(1, GameIdentity::count());
        }
        $otherCode = $linking->issueCode($second);
        $this->expectException(CommerceException::class);
        $linking->consume($server, $otherCode, $identity->uuid, 'FixturePlayer');
    }

    public function test_new_link_code_invalidates_the_previous_code(): void
    {
        $linking = app(AccountLinking::class);
        $user = $this->customer();
        $old = $linking->issueCode($user);
        $linking->issueCode($user);
        $this->assertSame(1, LinkCode::count());
        $this->expectException(CommerceException::class);
        $linking->consume($this->server(), $old, '12345678-1234-4234-8234-123456789abc', 'FixturePlayer');
    }

    public function test_player_command_arguments_cannot_inject_an_additional_console_command(): void
    {
        $this->expectException(CommerceException::class);
        CommandTemplate::render('lp user {player} parent add explorer', ['player' => "Fixture\nop Attacker"]);
    }
}
