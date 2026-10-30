<?php

namespace App\Http\Middleware;

use App\Core\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('vibecraft.locales');
        // @phpstan-ignore nullsafe.neverNull (Keep explicit guest handling alongside the coalescing fallback.)
        $locale = $request->user()?->locale
            ?? ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? $this->settings->get('site.default_locale');

        app()->setLocale(in_array($locale, $supported, true) ? $locale : 'tr');

        return $next($request);
    }
}
