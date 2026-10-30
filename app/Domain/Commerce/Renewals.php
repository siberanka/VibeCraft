<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Wallet-funded renewal of timed ranks. The player opts in at a price shown to
 * them; each period is charged at most once (ledger key per entitlement and
 * period) and only extends the existing entitlement, so no command is re-sent.
 * A failed charge switches renewal off and lets the normal expiry run.
 */
class Renewals
{
    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    public function enable(User $user, Entitlement $entitlement): void
    {
        $entitlement->loadMissing('orderItem.product');
        $product = $entitlement->orderItem?->product;
        if ($entitlement->user_id !== $user->id || $entitlement->status !== 'active' || ! $entitlement->orderItem?->duration_days || $product === null || ! $product->is_visible) {
            throw new CommerceException(__('This rank cannot renew automatically.'));
        }
        // A bundle bought with quantity N renews N periods at once, so it is charged N unit prices.
        $price = self::periodPrice($product->price_minor, (int) $entitlement->orderItem->quantity);
        $entitlement->forceFill(['auto_renew' => true, 'renew_price_minor' => $price])->save();
        $this->audit->log('renewal.enabled', $entitlement, ['price' => $price], $user->id);
    }

    public static function periodPrice(int $unitPriceMinor, int $quantity): int
    {
        $quantity = max(1, $quantity);
        if ($unitPriceMinor < 0 || $unitPriceMinor > intdiv(Pricing::MAX_TOTAL_MINOR, $quantity)) {
            throw new CommerceException(__('This rank cannot renew automatically.'));
        }

        return $unitPriceMinor * $quantity;
    }

    public function disable(User $user, Entitlement $entitlement): void
    {
        if ($entitlement->user_id !== $user->id) {
            throw new CommerceException(__('This rank cannot renew automatically.'));
        }
        $entitlement->forceFill(['auto_renew' => false])->save();
        $this->audit->log('renewal.disabled', $entitlement, [], $user->id);
    }

    /** Renews due entitlements before the expiry job runs. */
    public function renewDue(int $limit = 200): int
    {
        $renewed = 0;
        Entitlement::query()->where('status', 'active')->where('auto_renew', true)->where('expires_at', '<=', now())
            ->with(['orderItem', 'user'])->limit($limit)->get()->each(function (Entitlement $e) use (&$renewed) {
                DB::transaction(function () use ($e, &$renewed) {
                    $locked = Entitlement::query()->whereKey($e->id)->lockForUpdate()->first();
                    $days = $e->orderItem?->duration_days;
                    if ($locked === null || $locked->status !== 'active' || ! $locked->auto_renew || $locked->expires_at?->isFuture() || ! $days || (int) $locked->renew_price_minor < 1) {
                        return;
                    }
                    if ($e->user === null || $e->user->isBanned()) {
                        // A suspended account cannot opt out, so its renewals stop instead of charging it.
                        $locked->forceFill(['auto_renew' => false])->save();
                        $this->audit->log('renewal.stopped', $locked, ['reason' => 'account_suspended'], null);

                        return;
                    }
                    $period = $locked->renewals + 1;
                    try {
                        $this->ledger->post($locked->user_id, -(int) $locked->renew_price_minor, 'renewal', "renew:{$locked->id}:{$period}", note: 'Rank renewal #'.$period);
                    } catch (CommerceException) {
                        $locked->forceFill(['auto_renew' => false])->save();
                        $this->audit->log('renewal.failed', $locked, ['period' => $period], $locked->user_id);

                        return;
                    }
                    $locked->forceFill(['expires_at' => $locked->expires_at->copy()->addDays($days * max(1, (int) $e->orderItem->quantity)), 'renewals' => $period])->save();
                    $this->audit->log('renewal.charged', $locked, ['period' => $period, 'amount' => $locked->renew_price_minor], $locked->user_id);
                    $renewed++;
                });
            });

        return $renewed;
    }
}
