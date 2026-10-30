<?php

namespace App\Domain\Captcha;

use App\Core\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Server-side CAPTCHA verification (Turnstile, hCaptcha, reCAPTCHA v2).
 * A widget on the page proves nothing; only a successful siteverify call does.
 */
class CaptchaVerifier
{
    public const ENDPOINTS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha' => 'https://api.hcaptcha.com/siteverify',
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    public const FIELDS = [
        'turnstile' => 'cf-turnstile-response',
        'hcaptcha' => 'h-captcha-response',
        'recaptcha' => 'g-recaptcha-response',
    ];

    public function __construct(private Settings $settings) {}

    public function provider(): ?string
    {
        $provider = (string) $this->settings->get('captcha.provider');

        return isset(self::ENDPOINTS[$provider]) && $this->settings->hasSecret('captcha.secret') && $this->settings->get('captcha.site_key')
            ? $provider : null;
    }

    public function enabledFor(string $form): bool
    {
        $configured = (string) $this->settings->get('captcha.provider', 'none');

        return $configured !== 'none' && $configured !== '' && in_array($form, (array) $this->settings->get('captcha.forms'), true);
    }

    /** @throws ValidationException */
    public function verifyOrFail(Request $request, string $form): void
    {
        if (! $this->enabledFor($form)) {
            return;
        }

        $provider = $this->provider();
        if ($provider === null) {
            throw ValidationException::withMessages(['captcha' => __('The security check is not configured. Please contact support.')]);
        }
        $token = $request->input(self::FIELDS[$provider], '');

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            throw ValidationException::withMessages(['captcha' => __('Please complete the security check.')]);
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINTS[$provider], [
                'secret' => $this->settings->secret('captcha.secret'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
            $result = $response->json();
        } catch (Throwable) {
            Log::warning('captcha.unreachable', ['provider' => $provider]);
            throw ValidationException::withMessages(['captcha' => __('The security check service is unavailable. Please try again shortly.')]);
        }

        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true || ! $this->hostnameMatches($result)
            || ($provider === 'turnstile' && ($result['action'] ?? null) !== $form) || ! $this->challengeTimeValid($result)) {
            Log::notice('captcha.rejected', ['provider' => $provider]);
            throw ValidationException::withMessages(['captcha' => __('The security check failed. Please try again.')]);
        }
    }

    /** @param array<string, mixed> $result */
    private function hostnameMatches(array $result): bool
    {
        $hostname = $result['hostname'] ?? null;

        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($hostname) && is_string($canonical) && $canonical !== '' && strcasecmp($hostname, $canonical) === 0;
    }

    private function challengeTimeValid(array $result): bool
    {
        if (! array_key_exists('challenge_ts', $result)) {
            return true;
        }
        $value = $result['challenge_ts'];
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return false;
        }
        try {
            $timestamp = (new \DateTimeImmutable($value))->getTimestamp();
        } catch (Throwable) {
            return false;
        }

        return $timestamp >= now()->timestamp - 300 && $timestamp <= now()->timestamp + 60;
    }
}
