<?php

namespace App\Http\Controllers\Api;

use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\DeliveryQueue;
use App\Domain\Minecraft\AccountLinking;
use App\Domain\Minecraft\PlayerName;
use App\Domain\Minecraft\PlayerStatistics;
use App\Domain\Minecraft\Punishments;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\GameServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BridgeController extends Controller
{
    public function __construct(private DeliveryQueue $queue) {}

    public function poll(Request $request): JsonResponse
    {
        $server = $this->server($request);
        $data = $request->validate([
            'online' => ['array', 'max:5000'],
            'online.*' => ['string', 'max:36'],
            'players' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_players' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'plugin_version' => ['nullable', 'string', 'max:40'],
            'platform' => ['nullable', 'string', 'max:80'],
        ]);

        $online = array_values(array_filter(array_map(fn ($u) => PlayerName::normalizeUuid((string) $u), $data['online'] ?? [])));

        $server->forceFill([
            'bridge_last_seen_at' => now(),
            'bridge_plugin_version' => $data['plugin_version'] ?? $server->bridge_plugin_version,
            'bridge_platform' => $data['platform'] ?? $server->bridge_platform,
        ])->save();

        $deliveries = $this->queue->lease($server, $online, (int) config('vibecraft.bridge.poll_batch'));

        return response()->json([
            'deliveries' => $deliveries->map(fn (Delivery $d) => [
                'id' => $d->public_id,
                'lease_token' => $d->lease_token,
                'command' => $d->command,
                'player_uuid' => $d->player_uuid,
                'player_name' => $d->player_name,
                'require_online' => $d->require_online,
            ])->values(),
            'lease_seconds' => (int) config('vibecraft.bridge.lease_seconds'),
            'poll_after' => $deliveries->count() >= (int) config('vibecraft.bridge.poll_batch') ? 1 : 15,
        ]);
    }

    public function ack(Request $request): JsonResponse
    {
        $server = $this->server($request);
        $data = $request->validate([
            'results' => ['required', 'array', 'max:100'],
            'results.*.id' => ['required', 'string', 'size:26'],
            'results.*.lease_token' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'results.*.status' => ['required', 'in:delivered,failed,deferred,uncertain'],
            'results.*.message' => ['nullable', 'string', 'max:500'],
        ]);

        $accepted = [];
        foreach ($data['results'] as $result) {
            $delivery = $this->queue->acknowledge($server, strtolower($result['id']), $result['lease_token'], $result['status'], $result['message'] ?? null);
            if ($delivery !== null) {
                $accepted[] = $result['id'];
            }
        }

        return response()->json(['accepted' => $accepted]);
    }

    public function stats(Request $request, PlayerStatistics $statistics): JsonResponse
    {
        $data = $request->validate([
            'players' => ['present', 'array', 'max:500'],
            'players.*.uuid' => ['required', 'string', 'max:36'],
            'players.*.name' => ['required', 'string', 'max:32'],
            'players.*.stats' => ['required', 'array', 'max:8'],
        ]);

        return response()->json(['stored' => $statistics->record($this->server($request), $data['players'])]);
    }

    public function punishments(Request $request, Punishments $punishments): JsonResponse
    {
        $data = $request->validate([
            'bans' => ['present', 'array', 'max:5000'],
            'bans.*' => ['array'],
        ]);

        return response()->json(['stored' => $punishments->replace($this->server($request), $data['bans'])]);
    }

    public function link(Request $request, AccountLinking $linking): JsonResponse
    {
        $server = $this->server($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'uuid' => ['required', 'string', 'max:36'],
            'name' => ['required', 'string', 'max:32'],
            'edition' => ['nullable', 'in:java,bedrock'],
            'xuid' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $identity = $linking->consume($server, $data['code'], $data['uuid'], $data['name'], $data['edition'] ?? 'java', $data['xuid'] ?? null);
        } catch (CommerceException $e) {
            return response()->json(['ok' => false, 'reason' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'account' => $identity->user->name]);
    }

    private function server(Request $request): GameServer
    {
        return $request->attributes->get('bridge_server');
    }
}
