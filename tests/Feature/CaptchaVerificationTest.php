<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Captcha\CaptchaVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CaptchaVerificationTest extends TestCase
{
    private function configure(): CaptchaVerifier
    {
        app(Settings::class)->setMany(['captcha.provider' => 'turnstile', 'captcha.site_key' => 'fixture-site', 'captcha.forms' => ['register']]);
        app(Settings::class)->setSecret('captcha.secret', 'fixture-secret');

        return app(CaptchaVerifier::class);
    }

    private function request(): Request
    {
        return Request::create('https://attacker.example/register', 'POST', ['cf-turnstile-response' => 'fixture-token']);
    }

    public function test_configured_captcha_with_missing_secret_fails_closed(): void
    {
        app(Settings::class)->setMany(['captcha.provider' => 'turnstile', 'captcha.site_key' => 'fixture-site', 'captcha.forms' => ['register']]);
        Http::fake();
        $this->expectException(ValidationException::class);
        app(CaptchaVerifier::class)->verifyOrFail($this->request(), 'register');
    }

    public function test_correct_hostname_action_and_timestamp_are_accepted(): void
    {
        $verifier = $this->configure();
        Http::fake([CaptchaVerifier::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'hostname' => 'vibecraft.example', 'action' => 'register', 'challenge_ts' => now()->toIso8601String()])]);
        $verifier->verifyOrFail($this->request(), 'register');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['response'] === 'fixture-token' && $request['secret'] === 'fixture-secret');
    }

    public function test_success_for_a_forged_request_host_is_rejected(): void
    {
        $verifier = $this->configure();
        Http::fake([CaptchaVerifier::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'hostname' => 'attacker.example', 'action' => 'register'])]);
        $this->expectException(ValidationException::class);
        $verifier->verifyOrFail($this->request(), 'register');
    }

    public function test_token_issued_for_a_different_action_is_rejected(): void
    {
        $verifier = $this->configure();
        Http::fake([CaptchaVerifier::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'hostname' => 'vibecraft.example', 'action' => 'login'])]);
        $this->expectException(ValidationException::class);
        $verifier->verifyOrFail($this->request(), 'register');
    }

    public function test_stale_challenge_is_rejected_even_when_success_is_true(): void
    {
        $verifier = $this->configure();
        Http::fake([CaptchaVerifier::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'hostname' => 'vibecraft.example', 'action' => 'register', 'challenge_ts' => now()->subMinutes(6)->toIso8601String()])]);
        $this->expectException(ValidationException::class);
        $verifier->verifyOrFail($this->request(), 'register');
    }

    public function test_http_error_with_success_json_is_rejected(): void
    {
        $verifier = $this->configure();
        app(Settings::class)->set('captcha.fail_mode', 'open');
        $this->app->instance('env', 'production');
        Http::fake([CaptchaVerifier::ENDPOINTS['turnstile'] => Http::response(['success' => true, 'hostname' => 'vibecraft.example', 'action' => 'register'], 503)]);
        $this->expectException(ValidationException::class);
        $verifier->verifyOrFail($this->request(), 'register');
    }
}
