<?php

namespace App\Domain\Bridge;

/**
 * Request/response signing shared with the in-game bridge plugin.
 *
 * request  : HMAC_SHA256(secret, METHOD \n PATH \n TIMESTAMP \n NONCE \n SHA256(BODY))
 * response : HMAC_SHA256(secret, "response" \n TIMESTAMP \n REQUEST_NONCE \n SHA256(BODY))
 *
 * Signing responses lets the plugin refuse commands that did not come from this
 * site even if TLS were intercepted.
 */
final class BridgeSignature
{
    public static function request(string $secret, string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $body), $secret);
    }

    public static function response(string $secret, int $timestamp, string $requestNonce, string $body): string
    {
        return hash_hmac('sha256', "response\n".$timestamp."\n".$requestNonce."\n".hash('sha256', $body), $secret);
    }
}
