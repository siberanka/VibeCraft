<?php

namespace App\Http\Middleware;

use App\Core\Install\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = Installation::isInstalled();
        $isInstallRoute = $request->is('install', 'install/*');

        if (! $installed && ! $isInstallRoute && ! $request->is('up', 'assets/*', 'themes/*', 'fonts/*')) {
            if ($request->is('api/*')) {
                return response()->json(['error' => 'installation_required'], 503)->header('Retry-After', '60')->header('Cache-Control', 'no-store');
            }
            return redirect('/install');
        }

        if ($installed && $isInstallRoute) {
            abort(404);
        }

        return $next($request);
    }
}
