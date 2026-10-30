<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Models\GiftCode;
use App\Models\GiftCodeRedemption;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Credit codes ("kod bozdur"). A code carries 80 random bits, so a plain SHA-256
 * digest is enough to store it; the clear code exists only in the creation
 * response. Redemption locks the code row and posts through the ledger with a
 * per-user idempotency key, so one account can never redeem a code twice and a
 * code can never exceed its redemption limit.
 */
class GiftCodes
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    public static function digest(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /**
     * @return list<string> the clear codes, shown once
     */
    public function generate(int $count, int $amountMinor, int $maxRedemptions, ?Carbon $expiresAt, ?string $note, int $actorId): array
    {
        if ($count < 1 || $count > 200 || $amountMinor < 1 || $amountMinor > (int) config('vibecraft.store.max_topup_minor') || $maxRedemptions < 1 || $maxRedemptions > 10000) {
            throw new CommerceException(__('The code settings are out of range.'));
        }

        $codes = [];
        DB::transaction(function () use ($count, $amountMinor, $maxRedemptions, $expiresAt, $note, $actorId, &$codes) {
            while (count($codes) < $count) {
                $raw = '';
                for ($i = 0; $i < 16; $i++) {
                    $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
                }
                GiftCode::query()->create([
                    'code_hash' => self::digest($raw),
                    'code_hint' => substr($raw, -4),
                    'amount_minor' => $amountMinor,
                    'max_redemptions' => $maxRedemptions,
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                    'created_by' => $actorId,
                    'note' => $note !== null ? mb_substr($note, 0, 120) : null,
                ]);
                $codes[] = implode('-', str_split($raw, 4));
            }
        });

        $this->audit->log('gift_codes.generated', null, ['count' => $count, 'amount' => $amountMinor, 'max_redemptions' => $maxRedemptions], $actorId);

        return $codes;
    }

    public function redeem(User $user, string $code): GiftCodeRedemption
    {
        $normalized = self::normalize($code);
        if (strlen($normalized) !== 16) {
            throw new CommerceException(__('This code is not valid.'));
        }

        try {
            return DB::transaction(function () use ($user, $normalized) {
                /** @var GiftCode|null $gift */
                $gift = GiftCode::query()->where('code_hash', hash('sha256', $normalized))->lockForUpdate()->first();

                // One message for unknown, inactive, expired and exhausted codes: no enumeration.
                if ($gift === null || ! $gift->is_active || ($gift->expires_at !== null && $gift->expires_at->isPast()) || $gift->redemptions >= $gift->max_redemptions) {
                    throw new CommerceException(__('This code is not valid.'));
                }
                if (GiftCodeRedemption::query()->where('gift_code_id', $gift->id)->where('user_id', $user->id)->exists()) {
                    throw new CommerceException(__('You have already used this code.'));
                }

                $entry = $this->ledger->post($user->id, $gift->amount_minor, 'gift_code', "gift:{$gift->id}:user:{$user->id}", note: 'Code …'.$gift->code_hint);
                $redemption = GiftCodeRedemption::query()->create([
                    'gift_code_id' => $gift->id, 'user_id' => $user->id, 'wallet_entry_id' => $entry->id, 'amount_minor' => $gift->amount_minor,
                ]);
                $gift->increment('redemptions');
                $this->audit->log('gift_code.redeemed', $gift, ['amount' => $gift->amount_minor], $user->id);

                return $redemption;
            });
        } catch (UniqueConstraintViolationException) {
            throw new CommerceException(__('You have already used this code.'));
        }
    }

    public function deactivate(GiftCode $gift, int $actorId): void
    {
        $gift->forceFill(['is_active' => false])->save();
        $this->audit->log('gift_code.deactivated', $gift, [], $actorId);
    }
}
