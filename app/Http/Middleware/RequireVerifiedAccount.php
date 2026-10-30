<?php

namespace App\Http\Middleware;

use App\Core\Settings\Settings;
use Closure;
use Illuminate\Http\Request;

class RequireVerifiedAccount
{
    public function handle(Request $request, Closure $next)
    {
        if (app(Settings::class)->bool('auth.require_email_verification') && ! $request->user()?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }
        return $next($request);
    }
}
