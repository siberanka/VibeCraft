<?php

use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\AuthenticateBridge;
use App\Http\Middleware\AuthFormProtection;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureNotBanned;
use App\Http\Middleware\PrivateCache;
use App\Http\Middleware\RequireNavigation;
use App\Http\Middleware\RequireStaffTwoFactor;
use App\Http\Middleware\RequireVerifiedAccount;
use App\Http\Middleware\ResolveClientIp;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->authenticateSessions();
        $middleware->trimStrings(except: ['body_markdown']);
        $middleware->prepend([
            AssignCorrelationId::class,
            ValidateHost::class,
            ResolveClientIp::class,
            SecurityHeaders::class,
            PrivateCache::class,
            EnsureInstalled::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            EnsureNotBanned::class,
            AuthFormProtection::class,
        ]);
        $middleware->alias([
            'bridge' => AuthenticateBridge::class,
            'staff.2fa' => RequireStaffTwoFactor::class,
            'account.verified' => RequireVerifiedAccount::class,
            'navigation' => RequireNavigation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response): Response {
            if ($response->getStatusCode() >= 400) {
                $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
            }

            return $response;
        });
    })->create();
