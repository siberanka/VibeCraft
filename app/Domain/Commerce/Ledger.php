<?php

namespace App\Domain\Commerce;

use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Append-only wallet ledger.
 *
 * Every posting carries an idempotency key; posting the same key twice returns the
 * original entry instead of moving money again. The wallet row is locked for the
 * duration of the posting, so concurrent spends cannot overdraw the balance.
 */
class Ledger
{
    public function balance(int $userId): int
    {
        return (int) (Wallet::query()->whereKey($userId)->value('balance_minor') ?? 0);
    }

    public function post(
        int $userId,
        int $amountMinor,
        string $type,
        string $idempotencyKey,
        ?int $orderId = null,
        ?int $actorId = null,
        ?string $note = null,
        bool $allowNegative = false,
    ): WalletEntry {
        if ($amountMinor === 0 || $amountMinor > Pricing::MAX_TOTAL_MINOR || $amountMinor < -Pricing::MAX_TOTAL_MINOR
            || $idempotencyKey === '' || strlen($idempotencyKey) > 120
            || ! in_array($type, ['topup', 'purchase', 'refund', 'adjustment', 'reversal', 'gift_code', 'renewal', 'bonus'], true)) {
            throw new CommerceException(__('The ledger posting is not valid.'));
        }

        try {
            return DB::transaction(function () use ($userId, $amountMinor, $type, $idempotencyKey, $orderId, $actorId, $note, $allowNegative) {
                $existing = WalletEntry::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->samePosting($existing, $userId, $amountMinor, $type, $orderId);
                }

                Wallet::query()->insertOrIgnore(['user_id' => $userId, 'balance_minor' => 0, 'created_at' => now(), 'updated_at' => now()]);
                /** @var Wallet $wallet */
                $wallet = Wallet::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

                $existing = WalletEntry::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing !== null) {
                    return $this->samePosting($existing, $userId, $amountMinor, $type, $orderId);
                }

                $newBalance = $wallet->balance_minor + $amountMinor;
                if ($newBalance > Pricing::MAX_TOTAL_MINOR || $newBalance < -Pricing::MAX_TOTAL_MINOR) {
                    throw new CommerceException(__('The wallet balance exceeds the supported limit.'));
                }
                if ($newBalance < 0 && ! $allowNegative) {
                    throw new CommerceException(__('Insufficient balance.'));
                }

                $entry = WalletEntry::query()->create([
                    'user_id' => $userId,
                    'amount_minor' => $amountMinor,
                    'balance_after_minor' => $newBalance,
                    'type' => $type,
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $orderId,
                    'actor_id' => $actorId,
                    'note' => $note !== null ? mb_substr($note, 0, 255) : null,
                ]);

                $wallet->forceFill(['balance_minor' => $newBalance])->save();

                return $entry;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            // A concurrent request posted the same key first.
            return $this->samePosting(WalletEntry::query()->where('idempotency_key', $idempotencyKey)->firstOrFail(), $userId, $amountMinor, $type, $orderId);
        }
    }

    private function samePosting(WalletEntry $entry, int $userId, int $amount, string $type, ?int $orderId): WalletEntry
    {
        if ((int) $entry->user_id !== $userId || $entry->amount_minor !== $amount || $entry->type !== $type
            || ($entry->order_id !== null ? (int) $entry->order_id : null) !== $orderId) {
            throw new CommerceException(__('The transaction reference has already been used for a different posting.'));
        }

        return $entry;
    }

    /** Recomputes balances from the ledger; returns users whose cached balance drifted. */
    public function reconcile(): array
    {
        $drift = [];
        $sums = WalletEntry::query()->selectRaw('user_id, SUM(amount_minor) AS total')->groupBy('user_id')->pluck('total', 'user_id');
        foreach (Wallet::query()->get() as $wallet) {
            $expected = (int) ($sums[$wallet->user_id] ?? 0);
            if ($expected !== $wallet->balance_minor) {
                $drift[$wallet->user_id] = ['cached' => $wallet->balance_minor, 'ledger' => $expected];
            }
        }

        return $drift;
    }
}
