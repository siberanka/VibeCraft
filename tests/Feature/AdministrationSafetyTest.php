<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Models\Delivery;
use App\Models\GameServer;
use App\Models\Order;
use Tests\TestCase;

class AdministrationSafetyTest extends TestCase
{
    private function staffSession(string $role)
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $user = $this->customer($role);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    public function test_support_cannot_promote_accounts_or_ban_customers(): void
    {
        $this->staffSession('support');
        $target = $this->customer();
        $this->post('/admin/users/'.$target->id.'/action', ['action' => 'role', 'role' => 'owner'])->assertForbidden();
        $this->post('/admin/users/'.$target->id.'/action', ['action' => 'ban', 'reason' => 'Fixture denial'])->assertForbidden();
        $this->assertFalse($target->fresh()->hasRole('owner'));
        $this->assertFalse($target->fresh()->isBanned());
    }

    public function test_last_owner_cannot_demote_or_ban_their_own_account(): void
    {
        $owner = $this->staffSession('owner');
        $this->post('/admin/users/'.$owner->id.'/action', ['action' => 'role', 'role' => 'member'])->assertStatus(409);
        $this->post('/admin/users/'.$owner->id.'/action', ['action' => 'ban', 'reason' => 'Fixture denial'])->assertForbidden();
        $this->assertTrue($owner->fresh()->hasRole('owner'));
        $this->assertFalse($owner->fresh()->isBanned());
    }

    public function test_moderator_cannot_ban_another_staff_member(): void
    {
        $this->staffSession('moderator');
        $target = $this->customer('support');
        $this->post('/admin/users/'.$target->id.'/action', ['action' => 'ban', 'reason' => 'Fixture denial'])->assertForbidden();
        $this->assertFalse($target->fresh()->isBanned());
    }

    public function test_refunded_purchase_cannot_be_retried_even_by_an_owner(): void
    {
        $owner = $this->staffSession('owner');
        $order = $this->pendingOrder($owner, kind: 'purchase');
        $order->forceFill(['status' => Order::REFUNDED])->save();
        $server = GameServer::forceCreate(['name' => 'Admin fixture', 'slug' => 'admin-fixture',
            'bridge_key_id' => 'vck_'.bin2hex(random_bytes(12)), 'bridge_secret' => 'fixture-admin-key']);
        $delivery = Delivery::create(['order_id' => $order->id, 'game_server_id' => $server->id, 'phase' => 'purchase',
            'command' => 'say fixture', 'dedup_key' => 'fixture:admin-delivery', 'status' => Delivery::MANUAL_REVIEW]);
        $this->post('/admin/deliveries/'.$delivery->id.'/action', ['action' => 'retry'])->assertStatus(409);
        $this->assertSame(Delivery::MANUAL_REVIEW, $delivery->fresh()->status);
    }

    public function test_banned_user_session_is_revoked_on_next_request(): void
    {
        $user = $this->customer();
        $user->forceFill(['banned_at' => now(), 'ban_reason' => 'Fixture ban'])->save();
        $this->actingAs($user)->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
