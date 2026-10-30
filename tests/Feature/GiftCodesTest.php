<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\GiftCodes;
use App\Domain\Commerce\Ledger;
use App\Models\GiftCode;
use App\Models\WalletEntry;
use Tests\TestCase;

class GiftCodesTest extends TestCase
{
    private function codes(): GiftCodes
    {
        return app(GiftCodes::class);
    }

    public function test_generated_codes_are_stored_only_as_digests_and_credit_the_ledger_once(): void
    {
        $owner = $this->customer('owner');
        [$code] = $this->codes()->generate(1, 2500, 1, null, 'launch', $owner->id);

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{4}(-[A-Z2-9]{4}){3}$/', $code);
        $this->assertDatabaseMissing('gift_codes', ['code_hint' => $code]);
        $this->assertSame(GiftCodes::digest($code), GiftCode::query()->sole()->code_hash);

        $player = $this->customer();
        $this->codes()->redeem($player, strtolower(str_replace('-', ' ', $code)));

        $this->assertSame(2500, app(Ledger::class)->balance($player->id));
        $this->assertSame(1, GiftCode::query()->sole()->redemptions);

        $other = $this->customer();
        try {
            $this->codes()->redeem($other, $code);
            $this->fail('An exhausted code was accepted.');
        } catch (CommerceException $e) {
            $this->assertSame(__('This code is not valid.'), $e->getMessage());
        }
        $this->assertSame(0, app(Ledger::class)->balance($other->id));
    }

    public function test_one_account_cannot_redeem_a_multi_use_code_twice(): void
    {
        $owner = $this->customer('owner');
        [$code] = $this->codes()->generate(1, 500, 5, null, null, $owner->id);
        $player = $this->customer();

        $this->codes()->redeem($player, $code);
        $this->expectException(CommerceException::class);
        try {
            $this->codes()->redeem($player, $code);
        } finally {
            $this->assertSame(500, app(Ledger::class)->balance($player->id));
            $this->assertSame(1, WalletEntry::query()->where('user_id', $player->id)->count());
        }
    }

    public function test_expired_disabled_and_unknown_codes_share_one_answer(): void
    {
        $owner = $this->customer('owner');
        [$expired] = $this->codes()->generate(1, 100, 1, now()->addMinute(), null, $owner->id);
        [$disabled] = $this->codes()->generate(1, 100, 1, null, null, $owner->id);
        $this->travel(2)->minutes();
        $this->codes()->deactivate(GiftCode::query()->where('code_hash', GiftCodes::digest($disabled))->sole(), $owner->id);
        $player = $this->customer();

        $messages = [];
        foreach ([$expired, $disabled, 'AAAA-BBBB-CCCC-DDDD', 'short'] as $code) {
            try {
                $this->codes()->redeem($player, $code);
            } catch (CommerceException $e) {
                $messages[] = $e->getMessage();
            }
        }

        $this->assertSame(array_fill(0, 4, __('This code is not valid.')), $messages);
        $this->assertSame(0, app(Ledger::class)->balance($player->id));
    }

    public function test_redemption_endpoint_requires_login_and_is_rate_limited(): void
    {
        $this->post(route('account.gift-code'), ['code' => 'AAAA-BBBB-CCCC-DDDD'])->assertRedirect(route('login'));

        $player = $this->customer();
        foreach (range(1, 5) as $attempt) {
            $this->actingAs($player)->post(route('account.gift-code'), ['code' => 'AAAA-BBBB-CCCC-DDDD'])->assertRedirect();
        }
        $this->actingAs($player)->post(route('account.gift-code'), ['code' => 'AAAA-BBBB-CCCC-DDDD'])->assertStatus(429);
    }

    public function test_only_finance_staff_can_create_codes(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $support = $this->customer('support');
        $this->actingAs($support)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.gift-codes.store'), ['count' => 1, 'amount' => '10', 'max_redemptions' => 1])->assertForbidden();
        $this->assertSame(0, GiftCode::query()->count());
    }
}
