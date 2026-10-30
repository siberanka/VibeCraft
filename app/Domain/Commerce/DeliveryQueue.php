<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Models\Delivery;
use App\Models\GameServer;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lease/acknowledge protocol between the web and the in-game bridge.
 *
 * Expired leases keep their delivery id but receive a new receipt token. Acknowledgements
 * only apply to the lease they came from. Once-only game execution additionally needs
 * an agent's durable journal and reconciliation of uncertain command outcomes.
 */
class DeliveryQueue
{
    public function __construct(private Fulfillment $fulfillment, private AuditLogger $audit) {}

    /**
     * @param  list<string>  $onlinePlayerUuids
     * @return Collection<int, Delivery>
     */
    public function lease(GameServer $server, array $onlinePlayerUuids, int $limit): Collection
    {
        $limit = max(1, min(100, $limit));
        $leaseSeconds = (int) config('vibecraft.bridge.lease_seconds');

        return DB::transaction(function () use ($server, $onlinePlayerUuids, $limit, $leaseSeconds) {
            $now = now();
            $query = Delivery::query()
                ->where('game_server_id', $server->id)
                ->where(function ($q) use ($now) {
                    $q->where(fn ($q) => $q->where('status', Delivery::QUEUED)->where('available_at', '<=', $now))
                        ->orWhere(fn ($q) => $q->where('status', Delivery::LEASED)->where('lease_expires_at', '<', $now));
                })
                ->where(function ($q) use ($onlinePlayerUuids) {
                    $q->where('require_online', false);
                    if ($onlinePlayerUuids !== []) {
                        $q->orWhereIn('player_uuid', $onlinePlayerUuids);
                    }
                })
                ->orderBy('id')
                ->limit($limit);

            $selected = (clone $query)->get(['id', 'order_id']);
            // Refund/expiry and polling take order locks before delivery locks.
            $orders = Order::query()->whereIn('id', $selected->pluck('order_id')->filter()->unique())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $candidates = (clone $query)->whereIn('id', $selected->pluck('id'))->lockForUpdate()->get();

            $leased = collect();
            foreach ($candidates as $delivery) {
                $order = $delivery->order_id !== null ? $orders->get($delivery->order_id) : null;
                // A review hold stops new grants only; expiry and refund commands still run.
                $held = $order !== null && $delivery->phase === 'purchase' && $order->fulfillment_status === 'manual_review';
                if ($order !== null && ($held || ! $this->phaseAllowed($order, $delivery->phase))) {
                    if ($delivery->status === Delivery::LEASED || $delivery->attempts > 0) {
                        // It may already have run in game, so it needs reconciliation rather than silent cancellation.
                        $delivery->forceFill(['status' => Delivery::MANUAL_REVIEW, 'lease_expires_at' => null,
                            'last_error' => 'Stopped after it was sent; check the game server before retrying'])->save();
                    } else {
                        $delivery->forceFill(['status' => Delivery::CANCELLED, 'lease_expires_at' => null])->save();
                    }

                    continue;
                }
                if ($delivery->attempts >= $delivery->max_attempts) {
                    $delivery->forceFill(['status' => Delivery::DEAD, 'last_error' => 'Maximum attempts reached without acknowledgement'])->save();
                    $this->afterChange($delivery);

                    continue;
                }
                $delivery->forceFill([
                    'status' => Delivery::LEASED,
                    'attempts' => $delivery->attempts + 1,
                    'lease_token' => bin2hex(random_bytes(32)),
                    'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                ])->save();
                $leased->push($delivery);
            }

            return $leased;
        });
    }

    /**
     * @param  'delivered'|'failed'|'deferred'|'uncertain'  $result
     */
    public function acknowledge(GameServer $server, string $publicId, string $leaseToken, string $result, ?string $message = null): ?Delivery
    {
        return DB::transaction(function () use ($server, $publicId, $leaseToken, $result, $message) {
            $candidate = Delivery::query()->where('public_id', $publicId)->where('game_server_id', $server->id)->first();
            if ($candidate === null) {
                return null;
            }
            $order = $candidate->order_id !== null ? Order::query()->whereKey($candidate->order_id)->lockForUpdate()->first() : null;
            /** @var Delivery|null $delivery */
            $delivery = Delivery::query()->where('public_id', $publicId)->where('game_server_id', $server->id)->lockForUpdate()->first();
            if ($delivery === null) {
                return null;
            }
            $activeToken = $delivery->lease_token;
            if ($activeToken === null || ! hash_equals($activeToken, $leaseToken)) {
                return null;
            }
            if ($delivery->status === Delivery::DELIVERED) {
                return $delivery; // duplicate ACK — idempotent
            }

            if ($delivery->status !== Delivery::LEASED || $delivery->lease_expires_at === null || $delivery->lease_expires_at->isPast()
                || ($order !== null && ! $this->phaseAllowed($order, $delivery->phase))) {
                if (in_array($result, ['delivered', 'uncertain'], true) && $delivery->attempts > 0) {
                    $delivery->forceFill(['status' => Delivery::MANUAL_REVIEW, 'last_error' => 'Late game-server outcome requires reconciliation', 'lease_expires_at' => null])->save();
                    $order?->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Late game-server acknowledgement requires reconciliation'])->save();
                }

                return $delivery;
            }

            $message = $message !== null ? mb_substr($message, 0, 500) : null;

            switch ($result) {
                case 'uncertain':
                    $delivery->forceFill([
                        'status' => Delivery::MANUAL_REVIEW,
                        'lease_expires_at' => null,
                        'last_error' => $message ?? 'Game-server execution outcome is uncertain',
                    ])->save();
                    $order?->forceFill(['fulfillment_status' => 'manual_review', 'admin_note' => 'Game-server execution outcome requires reconciliation'])->save();
                    break;
                case 'delivered':
                    $delivery->forceFill(['status' => Delivery::DELIVERED, 'delivered_at' => now(), 'lease_expires_at' => null, 'last_error' => null])->save();
                    break;
                case 'deferred':
                    // Player offline for an online-only command: not an attempt.
                    $delivery->forceFill([
                        'status' => Delivery::QUEUED,
                        'attempts' => max(0, $delivery->attempts - 1),
                        'lease_expires_at' => null,
                        'available_at' => now()->addSeconds(30),
                    ])->save();
                    break;
                default:
                    $dead = $delivery->attempts >= $delivery->max_attempts;
                    $delivery->forceFill([
                        'status' => $dead ? Delivery::DEAD : Delivery::QUEUED,
                        'lease_expires_at' => null,
                        'available_at' => now()->addSeconds(min(3600, 30 * (2 ** min(10, $delivery->attempts)))),
                        'last_error' => $message ?? 'Reported as failed by the server',
                    ])->save();
            }

            $this->afterChange($delivery);

            return $delivery;
        });
    }

    public function retry(Delivery $delivery, ?int $actorId = null): void
    {
        $delivery->forceFill(['status' => Delivery::QUEUED, 'attempts' => 0, 'available_at' => now(), 'lease_expires_at' => null, 'lease_token' => null])->save();
        $this->audit->log('delivery.retried', $delivery, [], $actorId);
        $this->afterChange($delivery);
    }

    public function cancel(Delivery $delivery, ?int $actorId = null): void
    {
        $delivery->forceFill(['status' => Delivery::CANCELLED, 'lease_expires_at' => null])->save();
        $this->audit->log('delivery.cancelled', $delivery, [], $actorId);
        $this->afterChange($delivery);
    }

    private function afterChange(Delivery $delivery): void
    {
        if ($delivery->order_id !== null && ($order = Order::query()->find($delivery->order_id)) !== null) {
            $this->fulfillment->recompute($order);
        }
    }

    private function phaseAllowed(Order $order, string $phase): bool
    {
        return match ($phase) {
            'purchase', 'expire' => $order->status === Order::PAID,
            'refund' => in_array($order->status, [Order::REFUNDED, Order::DISPUTED], true),
            default => false,
        };
    }
}
