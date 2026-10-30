<?php

namespace App\Http\Middleware;

use App\Core\Install\Installation;
use Closure;
use Illuminate\Http\Request;

class ValidateHost
{
    public function handle(Request $request, Closure $next)
    {
        if (! Installation::isInstalled()) {
            // Before installation the owner confirms the site address in the installer.
            return $next($request);
        }
        $url = (string) config('app.url');
        $expected = parse_url($url, PHP_URL_HOST);
        abort_unless(is_string($expected) && strcasecmp($request->getHost(), $expected) === 0, 400, 'Unrecognized host.');
        // The port is part of the authority used in generated links (reset and verification mails).
        $header = (string) $request->headers->get('host');
        if (preg_match('/:(\d+)$/', $header, $match) === 1) {
            $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https://') ? 443 : 80);
            abort_unless((int) $match[1] === $port, 400, 'Unrecognized host.');
        }

        return $next($request);
    }
}
