<?php

namespace App\Http\Middleware;

use App\Domain\Bridge\BridgeSignature;
use App\Models\BridgeNonce;
use App\Models\GameServer;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a signed bridge request: per-server key, timestamp window, single-use
 * nonce and HMAC over method, path and body. The response is signed as well.
 */
class AuthenticateBridge
{
    public function handle(Request $request, Closure $next): Response
    {
        $keyId = (string) $request->header('X-VC-Key');
        $timestamp = (string) $request->header('X-VC-Timestamp');
        $nonce = (string) $request->header('X-VC-Nonce');
        $signature = (string) $request->header('X-VC-Signature');

        if (! preg_match('/^vck_[a-z0-9]{24}$/', $keyId) || ! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || ! preg_match('/^[0-9a-f]{64}$/', $signature)) {
            return $this->deny('malformed');
        }

        // Only the signed JSON body may carry input: an unsigned query string or form
        // encoding would otherwise be merged into the validated request data.
        $body = $request->getContent();
        $decoded = $body === '' ? [] : json_decode($body, true, 32);
        if ($request->getQueryString() !== null || ! $request->isJson() || ! is_array($decoded)) {
            return $this->deny('unsigned_input');
        }

        /** @var GameServer|null $server */
        $server = GameServer::query()->where('bridge_key_id', $keyId)->first();
        if ($server === null || ! $server->bridge_enabled) {
            return $this->deny('unknown_key');
        }

        if (abs(time() - (int) $timestamp) > (int) config('vibecraft.bridge.max_skew_seconds')) {
            return $this->deny('clock_skew');
        }

        $secret = (string) $server->bridge_secret;
        $expected = BridgeSignature::request($secret, $request->getMethod(), '/'.ltrim($request->path(), '/'), (int) $timestamp, $nonce, $request->getContent());
        if (! hash_equals($expected, $signature)) {
            Log::notice('bridge.bad_signature', ['server' => $server->id, 'ip' => $request->ip()]);

            return $this->deny('bad_signature');
        }

        try {
            BridgeNonce::query()->create(['game_server_id' => $server->id, 'nonce' => $nonce, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return $this->deny('replay');
        }

        $request->query->replace([]);
        $request->request->replace([]);
        $request->setJson(new InputBag($decoded));
        $request->attributes->set('bridge_server', $server);

        $response = $next($request);

        $now = time();
        $response->headers->set('X-VC-Timestamp', (string) $now);
        $response->headers->set('X-VC-Signature', BridgeSignature::response($secret, $now, $nonce, (string) $response->getContent()));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function deny(string $reason): Response
    {
        return response()->json(['error' => 'unauthorized', 'reason' => $reason], 401);
    }
}
