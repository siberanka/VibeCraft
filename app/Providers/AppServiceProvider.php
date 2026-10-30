<?php

namespace App\Providers;

use App\Core\Audit\AuditLogger;
use App\Core\Install\Installation;
use App\Core\Security\CspNonce;
use App\Core\Settings\Settings;
use App\Core\Theme\ThemeManager;
use App\Domain\Media\MediaLibrary;
use App\Models\User;
use App\View\SiteComposer;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->scoped(CspNonce::class);
        $this->app->scoped(ThemeManager::class);
        $this->app->singleton(AuditLogger::class);
        $this->app->scoped(SiteComposer::class);
        $this->app->scoped(MediaLibrary::class);
    }

    public function boot(): void
    {
        $this->app->useLangPath(resource_path('lang'));
        if (! Installation::isInstalled()) {
            config(['session.driver' => 'file', 'cache.default' => 'file']);
        }
        Password::defaults(fn () => Password::min(10)->letters()->numbers()->max(128));

        // The owner role implicitly holds every permission.
        Gate::before(fn ($user) => $user->hasRole('owner') ? true : null);

        Blade::directive('nonce', fn () => '<?php echo \'nonce="\'.e(app(\App\Core\Security\CspNonce::class)->get()).\'"\'; ?>');

        // Before installation APP_URL is still the example value; the installer follows the request.
        if ($this->app->isProduction() && (string) config('app.url') !== '' && Installation::isInstalled()) {
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
            // Links in mails must never take their authority from request headers.
            URL::forceRootUrl((string) config('app.url'));
        }

        $this->app->make(ThemeManager::class)->registerViews();
        View::composer(['theme::*', 'themed.*', 'admin.*'], SiteComposer::class);

        $this->rateLimits();

        Event::listen(Login::class, function (Login $event) {
            /** @var User $user */
            $user = $event->user;
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            if (Installation::isInstalled()) {
                app(AuditLogger::class)->log('auth.login', $user, [], $user->getAuthIdentifier());
            }
        });
    }

    private function rateLimits(): void
    {
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(5)->by($r->ip()));
        RateLimiter::for('install', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => [Limit::perMinute(3)->by($r->ip()), Limit::perHour(10)->by(mb_strtolower((string) $r->input('email')))]);
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('tickets', fn (Request $r) => Limit::perHour(10)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('wiki-feedback', fn (Request $r) => Limit::perMinute(20)->by($r->user()->id));
        RateLimiter::for('discord-link', fn (Request $r) => Limit::perMinute(10)->by('discord:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('account-actions', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('forum', fn (Request $r) => [Limit::perMinute(4)->by('f'.($r->user()?->id ?: $r->ip())), Limit::perHour(40)->by('fh'.($r->user()?->id ?: $r->ip()))]);
        RateLimiter::for('gift-codes', fn (Request $r) => [Limit::perMinute(5)->by('u'.($r->user()?->id ?: $r->ip())), Limit::perHour(20)->by('uh'.($r->user()?->id ?: $r->ip())), Limit::perHour(40)->by('ip'.$r->ip())]);
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('bridge', fn (Request $r) => [
            Limit::perMinute(600)->by('peer:'.$r->ip()),
            Limit::perMinute(240)->by('key:'.substr((string) $r->header('X-VC-Key', ''), 0, 64)),
        ]);
        RateLimiter::for('public-json', fn (Request $r) => Limit::perMinute(60)->by($r->ip()));
    }
}
