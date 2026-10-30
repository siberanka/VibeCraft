<?php

namespace App\Http\Middleware;

use App\Core\Settings\Settings;
use App\Domain\Captcha\CaptchaVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AuthFormProtection
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('register')) {
            abort_unless(app(Settings::class)->bool('auth.registration'), 404);
        }
        if ($request->isMethod('POST') && $request->is('register', 'forgot-password')) {
            $key = 'auth-form:'.$request->path().':'.$request->ip();
            $limit = $request->is('register') ? 5 : 3;
            $seconds = $request->is('register') ? 3600 : 60;
            if (RateLimiter::tooManyAttempts($key, $limit)) { abort(429); }
            RateLimiter::hit($key, $seconds);
            if ($request->is('forgot-password')) { app(CaptchaVerifier::class)->verifyOrFail($request, 'forgot_password'); }
        }
        if ($this->checksCurrentPassword($request)) {
            // Fortify does not throttle these password checks; without a limit a stolen
            // session could guess the password and unlock the confirmed-password area.
            $owner = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
            foreach ([['password-check:m:'.$owner, 5], ['password-check:h:'.$owner, 20]] as [$key, $limit]) {
                if (RateLimiter::tooManyAttempts($key, $limit)) {
                    abort(429);
                }
            }
            RateLimiter::hit('password-check:m:'.$owner, 60);
            RateLimiter::hit('password-check:h:'.$owner, 3600);
        }

        return $next($request);
    }

    private function checksCurrentPassword(Request $request): bool
    {
        return ($request->isMethod('POST') && $request->is('user/confirm-password'))
            || ($request->isMethod('PUT') && $request->is('user/password', 'user/profile-information'));
    }
}
