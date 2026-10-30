<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personal and administrative pages must never be stored by a CDN or shared cache.
 */
class PrivateCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (($request->hasSession() && $request->user() !== null) || $request->is('admin', 'admin/*', 'legal', 'legal/*', 'account', 'account/*', 'cart', 'checkout*', 'install*', 'login', 'register', 'forgot-password', 'reset-password/*', 'two-factor-challenge', 'email/*', 'user/*', 'tickets*', 'orders*')) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
