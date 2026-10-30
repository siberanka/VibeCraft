<?php

namespace App\Domain\Commerce;

use App\Core\Settings\Settings;
use App\Models\User;

/**
 * Resolves the player an order is gifted to. The recipient must be another
 * active account with a verified Minecraft account, which becomes the
 * delivery target; the buyer still pays and owns the order record.
 */
final class GiftRecipient
{
    public static function resolve(User $buyer, ?string $name): ?User
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        if (! app(Settings::class)->bool('store.gifts')) {
            throw new CommerceException(__('Gifts are not available right now.'));
        }
        if (preg_match('/^[A-Za-z0-9_]{3,24}$/', $name) !== 1) {
            throw new CommerceException(__('We could not find that player.'));
        }
        $recipient = User::query()->where('name', $name)->whereNull('banned_at')->with('identities')->first();
        if ($recipient === null) {
            throw new CommerceException(__('We could not find that player.'));
        }
        if ($recipient->id === $buyer->id) {
            throw new CommerceException(__('You cannot send a gift to yourself.'));
        }
        if ($recipient->primaryIdentity()?->verified_at === null) {
            throw new CommerceException(__(':player has not linked a Minecraft account yet.', ['player' => $recipient->name]));
        }

        return $recipient;
    }
}
