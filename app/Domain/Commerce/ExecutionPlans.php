<?php

namespace App\Domain\Commerce;

use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCommand;
use App\Support\CanonicalJson;
use LogicException;
use Throwable;

/** Private original fulfillment definitions; never part of a customer document. */
final class ExecutionPlans
{
    public static function product(Product $product): array
    {
        $commands = $product->commands()->orderBy('sort')->orderBy('id')->limit(101)->get();
        if ($commands->count() > 100) {
            throw new CommerceException(__('This product has too many delivery steps to review.'));
        }

        return $commands->map(static fn (ProductCommand $command) => [
            'id' => (int) $command->id, 'server_id' => (int) $command->game_server_id,
            'phase' => (string) $command->phase, 'template' => (string) $command->command,
            'require_online' => (bool) $command->require_online,
        ])->all();
    }

    public static function capture(Order $order, OrderReview $review): array
    {
        $plans = $review->executionPlans();
        $items = [];
        foreach ($order->items()->orderBy('id')->get() as $item) {
            if (! array_key_exists($item->product_id, $plans)) {
                throw new LogicException('Missing reviewed execution definition.');
            }
            $items[] = ['order_item_id' => (int) $item->id, 'product_id' => (int) $item->product_id, 'commands' => $plans[$item->product_id]];
        }

        return ['format' => 1, 'items' => $items];
    }

    public static function valid(OrderContract $contract, Order $order): bool
    {
        try {
            $plan = $contract->execution_plan;
            if (! hash_equals($contract->execution_sha256, CanonicalJson::digest($plan))
                || ($plan['format'] ?? null) !== 1 || ! is_array($plan['items'] ?? null) || ! array_is_list($plan['items'])) {
                return false;
            }
            $items = $order->items()->get()->keyBy('id');
            $lines = collect($contract->snapshot['review']['lines'] ?? [])->keyBy('product_id');
            if ($items->count() !== count($plan['items'])) {
                return false;
            }
            $seen = [];
            foreach ($plan['items'] as $entry) {
                $id = $entry['order_item_id'] ?? null;
                $item = $items->get($id);
                $line = $lines->get($entry['product_id'] ?? null);
                if (! $item || ! $line || isset($seen[$id]) || (int) $item->product_id !== $entry['product_id']
                    || ! is_array($entry['commands'] ?? null) || ! array_is_list($entry['commands'])
                    || ! hash_equals($line['delivery_definition_sha256'] ?? '', CanonicalJson::digest($entry['commands']))) {
                    return false;
                }
                $seen[$id] = true;
            }

            return true;
        } catch (Throwable) {
            // An unreadable private plan holds execution; it must never fall back to the current catalogue.
            return false;
        }
    }

    /** Null means unavailable; an empty list means the original phase required no commands. */
    public static function commands(OrderContract $contract, OrderItem $item, string $phase): ?array
    {
        foreach ($contract->execution_plan['items'] as $entry) {
            if ($entry['order_item_id'] !== (int) $item->id) {
                continue;
            }
            $commands = array_values(array_filter($entry['commands'], static fn (array $command) => $command['phase'] === $phase));
            $serverIds = array_unique(array_column($commands, 'server_id'));
            if (GameServer::whereIn('id', $serverIds)->count() !== count($serverIds)) {
                return null;
            }

            return $commands;
        }

        return null;
    }
}
