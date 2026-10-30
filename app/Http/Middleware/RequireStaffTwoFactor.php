<?php

namespace App\Http\Middleware;

use App\Core\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffTwoFactor
{
    public function __construct(private Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->settings->bool('security.require_admin_2fa') && ! $user->hasTwoFactorEnabled()) {
            return redirect()->route('account.security')
                ->with('warning', __('Two-factor authentication is required for staff accounts. Enable it to access the panel.'));
        }

        return $next($request);
    }
}
