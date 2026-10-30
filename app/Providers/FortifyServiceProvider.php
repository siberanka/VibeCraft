<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domain\Captcha\CaptchaVerifier;
use App\Models\User;
use App\View\LegalSelection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::loginView(fn () => view('theme::auth.login'));
        Fortify::registerView(fn (Request $request) => view('theme::auth.register', app(LegalSelection::class)->forForm($request, 'registration')));
        Fortify::requestPasswordResetLinkView(fn () => view('theme::auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('theme::auth.reset-password', ['request' => $request]));
        Fortify::twoFactorChallengeView(fn () => view('theme::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('theme::auth.confirm-password'));
        Fortify::verifyEmailView(fn () => view('theme::auth.verify-email'));

        Fortify::authenticateUsing(function (Request $request) {
            app(CaptchaVerifier::class)->verifyOrFail($request, 'login');

            // Resolve the same dummy hash for every login, including known users.
            // Its cache identity changes with the configured driver and cost.
            $hashConfig = (array) config('hashing', []);
            $dummyKey = 'auth.dummy.'.hash('sha256', json_encode($hashConfig, JSON_THROW_ON_ERROR));
            $dummyHash = Cache::rememberForever($dummyKey, fn () => Hash::make(Str::random(64)));
            $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();
            // @phpstan-ignore nullsafe.neverNull (Keep explicit unknown-user handling alongside the dummy-hash fallback.)
            $valid = Hash::check((string) $request->input('password'), $user?->password ?? $dummyHash);
            if ($user === null || ! $valid) {
                Log::notice('auth.failed', ['ip' => $request->ip()]);

                return null;
            }
            if ($user->isBanned()) {
                throw ValidationException::withMessages(['email' => __('This account is suspended.')]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::username())).'|'.$request->ip());

            return [Limit::perMinute(5)->by($throttleKey), Limit::perMinute(30)->by($request->ip())];
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by((string) $request->session()->get('login.id')));
    }
}
