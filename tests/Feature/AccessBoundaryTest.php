<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Http\Middleware\ResolveClientIp;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccessBoundaryTest extends TestCase
{
    public function test_anonymous_account_redirect_is_never_cacheable(): void
    {
        $response = $this->get('/account')->assertRedirect();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_guest_is_redirected_and_customer_cannot_access_admin(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
        $this->actingAs($this->customer())->get('/admin')->assertForbidden();
    }

    public function test_owner_without_confirmed_two_factor_must_enroll_before_admin(): void
    {
        $this->actingAs($this->customer('owner'))->withSession(['auth.password_confirmed_at' => time()])
            ->get('/admin')->assertRedirect(route('account.security'));
    }

    public function test_support_role_cannot_use_finance_or_integration_settings(): void
    {
        app(Settings::class)->set('security.require_admin_2fa', false);
        $support = $this->customer('support');
        $order = $this->pendingOrder($this->customer());
        $this->actingAs($support)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/orders/'.$order->public_id.'/action', ['action' => 'refund', 'reason' => 'Fixture refusal'])->assertForbidden();
        $this->get('/admin/integrations')->assertForbidden();
    }

    public function test_order_and_browser_return_are_scoped_to_the_authenticated_owner(): void
    {
        $owner = $this->customer();
        $order = $this->pendingOrder($owner);
        $this->actingAs($this->customer())->get('/orders/'.$order->public_id)->assertNotFound();
        $this->get('/checkout/return/'.$order->public_id.'?paid=1')->assertNotFound();
        $this->actingAs($owner)->get('/checkout/return/'.$order->public_id.'?paid=1')->assertRedirect();
        $this->assertSame('pending_payment', $order->fresh()->status);
    }

    public function test_ticket_internal_notes_are_hidden_from_customers_and_other_accounts(): void
    {
        $user = $this->customer();
        $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Fixture support request', 'category' => 'general', 'last_activity_at' => now()]);
        $ticket->messages()->create(['user_id' => $user->id, 'body' => 'Visible customer reply']);
        $ticket->messages()->create(['user_id' => $this->customer('support')->id, 'body' => 'PRIVATE STAFF NOTE SENTINEL', 'is_staff' => true, 'is_internal' => true]);
        $this->actingAs($user)->get('/tickets/'.$ticket->public_id)->assertOk()->assertSee('Visible customer reply')->assertDontSee('PRIVATE STAFF NOTE SENTINEL');
        $this->actingAs($this->customer())->get('/tickets/'.$ticket->public_id)->assertNotFound();
    }

    public function test_account_responses_are_private_and_have_security_headers(): void
    {
        $response = $this->actingAs($this->customer())->get('/account')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_origin_client_cannot_forge_cloudflare_ip_or_https(): void
    {
        config(['vibecraft.proxy.mode' => 'cloudflare']);
        Request::setTrustedProxies([], 0);
        $request = Request::create('http://vibecraft.example', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.17', 'HTTP_CF_CONNECTING_IP' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '8.8.4.4', 'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $response = app(ResolveClientIp::class)->handle($request, fn (Request $r) => response()->json(['ip' => $r->ip(), 'https' => $r->isSecure()]));
        $data = json_decode($response->getContent(), true);
        $this->assertSame('192.0.2.17', $data['ip']);
        $this->assertFalse($data['https']);
        $this->assertFalse($request->headers->has('CF-Connecting-IP'));
    }

    public function test_unknown_login_is_a_validation_failure_and_registration_normalizes_email(): void
    {
        $this->postJson('/login', ['email' => 'missing@example.test', 'password' => 'WrongPassword123'])->assertUnprocessable();
        $this->customer()->forceFill(['email' => 'member@example.test'])->save();
        $this->postJson('/register', ['name' => 'AnotherMember', 'email' => 'MEMBER@EXAMPLE.TEST', 'password' => 'GoodPassword123', 'password_confirmation' => 'GoodPassword123', 'terms' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_mutating_web_route_requires_csrf_when_test_bypass_is_disabled(): void
    {
        $this->app->instance('env', 'production');
        $this->actingAs($this->customer())->post('/cart', ['product_id' => 1, 'quantity' => 1])->assertStatus(419);
    }

    public function test_forgot_password_is_rate_limited_before_repeated_broker_work(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/forgot-password', ['email' => 'missing@example.test']);
        }
        $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertStatus(429);
    }

    public function test_trusted_custom_proxy_chain_ignores_client_controlled_leftmost_spoof(): void
    {
        config(['vibecraft.proxy.mode' => 'custom', 'vibecraft.proxy.custom_cidrs' => ['10.0.0.0/8']]);
        Request::setTrustedProxies([], 0);
        $request = Request::create('http://vibecraft.example', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 192.0.2.10, 10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'http, https', 'HTTP_X_FORWARDED_HOST' => 'attacker.example',
        ]);
        $response = app(ResolveClientIp::class)->handle($request, fn (Request $r) => response()->json(['ip' => $r->ip(), 'https' => $r->isSecure()]));
        $data = json_decode($response->getContent(), true);
        $this->assertSame('192.0.2.10', $data['ip']);
        $this->assertTrue($data['https']);
        $this->assertFalse($request->headers->has('X-Forwarded-For'));
        $this->assertFalse($request->headers->has('X-Forwarded-Host'));
        $this->assertFalse($request->headers->has('X-Forwarded-Proto'));
    }

    public function test_untrusted_custom_proxy_claims_are_ignored_and_removed(): void
    {
        config(['vibecraft.proxy.mode' => 'custom', 'vibecraft.proxy.custom_cidrs' => ['10.0.0.0/8']]);
        Request::setTrustedProxies([], 0);
        $request = Request::create('http://vibecraft.example', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.0.2.20', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8', 'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $response = app(ResolveClientIp::class)->handle($request, fn (Request $r) => response()->json(['ip' => $r->ip(), 'https' => $r->isSecure()]));
        $data = json_decode($response->getContent(), true);
        $this->assertSame('192.0.2.20', $data['ip']);
        $this->assertFalse($data['https']);
        $this->assertFalse($request->headers->has('X-Forwarded-For'));
    }
}
