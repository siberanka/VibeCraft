<?php

namespace App\Http\Middleware;

use App\Core\Security\IpRange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts CF-Connecting-IP only when the TCP peer is a Cloudflare edge address.
 * A client talking to the origin directly cannot spoof its IP with this header.
 */
class ResolveClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $peer = (string) $request->server->get('REMOTE_ADDR');
        if (config('vibecraft.proxy.mode') === 'cloudflare') {
            $claimed = (string) $request->headers->get('CF-Connecting-IP');

            if ($claimed !== '' && filter_var($claimed, FILTER_VALIDATE_IP) && IpRange::contains($peer, IpRange::cloudflare())) {
                $request->server->set('REMOTE_ADDR', $claimed);
                if (strtolower((string) $request->headers->get('X-Forwarded-Proto')) === 'https') {
                    $request->server->set('HTTPS', 'on');
                }
            }
        }

        if (config('vibecraft.proxy.mode') === 'custom' && IpRange::contains($peer, config('vibecraft.proxy.custom_cidrs', []))) {
            $header = (string) $request->header('X-Forwarded-For');
            $chain = array_map('trim', explode(',', $header));
            // @phpstan-ignore notIdentical.alwaysTrue (Retain explicit chain shape validation at the proxy boundary.)
            if (strlen($header) <= 2048 && count($chain) <= 16 && $chain !== [] && collect($chain)->every(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false)) {
                $ranges = config('vibecraft.proxy.custom_cidrs', []);
                for ($i = count($chain) - 1; $i >= 0; $i--) {
                    $request->server->set('REMOTE_ADDR', $chain[$i]);
                    if (! IpRange::contains($chain[$i], $ranges)) { break; }
                }
                $protocols = array_map('trim', explode(',', (string) $request->header('X-Forwarded-Proto')));
                if (strtolower(end($protocols)) === 'https') { $request->server->set('HTTPS', 'on'); }
            }
        }

        // Never let a forged header leak into application code.
        $request->headers->remove('CF-Connecting-IP');
        $request->headers->remove('True-Client-IP');
        foreach (['Forwarded', 'X-Forwarded-For', 'X-Forwarded-Proto', 'X-Forwarded-Host', 'X-Forwarded-Port', 'X-Real-IP'] as $header) { $request->headers->remove($header); }

        return $next($request);
    }
}
