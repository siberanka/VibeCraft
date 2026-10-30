<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Web chest ("sandık"): paid items marked for chest delivery wait here until the
 * player claims them. Claiming queues the item's frozen delivery plan exactly once
 * and starts any timed entitlement at that moment.
 */
class Chest
{
    public function __construct(private Fulfillment $fulfillment, private AuditLogger $audit) {}

    /** @return Collection<int, OrderItem> */
    public function contents(User $user): Collection
    {
        return OrderItem::query()
            ->where('held_in_chest', true)->whereNull('chest_claimed_at')
            ->whereHas('order', fn ($q) => $q->where(fn ($owner) => $owner->where('gift_recipient_id', $user->id)->orWhere(fn ($own) => $own->whereNull('gift_recipient_id')->where('user_id', $user->id)))->where('kind', 'purchase')->where('status', Order::PAID)->where('fulfillment_status', '!=', 'manual_review'))
            ->with('order:id,public_id,paid_at')->orderBy('id')->get();
    }

    public function claim(User $user, int $itemId): OrderItem
    {
        return DB::transaction(function () use ($user, $itemId) {
            /** @var OrderItem|null $item */
            $item = OrderItem::query()->whereKey($itemId)->lockForUpdate()->first();
            $order = $item ? Order::query()->whereKey($item->order_id)->lockForUpdate()->first() : null;

            if ($item === null || $order === null || ($order->gift_recipient_id ?? $order->user_id) !== $user->id || ! $item->held_in_chest) {
                throw new CommerceException(__('This item is not in your chest.'));
            }
            if ($item->chest_claimed_at !== null) {
                throw new CommerceException(__('This item has already been claimed.'));
            }
            if ($order->status !== Order::PAID || $order->fulfillment_status === 'manual_review') {
                throw new CommerceException(__('This item cannot be claimed right now. Contact support.'));
            }

            $item->forceFill(['chest_claimed_at' => now()])->save();
            $created = $this->fulfillment->enqueue($item, 'purchase');
            $this->fulfillment->grantEntitlement($order, $item);
            if ($created > 0) {
                $order->forceFill(['fulfillment_status' => 'queued'])->save();
            }
            $this->audit->log('chest.claimed', $item, ['order' => $order->public_id, 'deliveries' => $created], $user->id);

            return $item;
        });
    }
}
