<?php

namespace App\Domain\Minecraft;

use App\Core\Security\PublicEndpoint;

/**
 * Bedrock Edition RakNet "unconnected ping" over UDP.
 */
class BedrockPinger
{
    private const MAGIC = "\x00\xff\xff\x00\xfe\xfe\xfe\xfe\xfd\xfd\xfd\xfd\x12\x34\x56\x78";

    public function ping(string $host, int $port, float $timeout = 3.0): StatusResult
    {
        $deadline = new NetworkDeadline($timeout);
        $endpoint = PublicEndpoint::address($host, $port);
        if ($deadline->remaining() <= 0) {
            return StatusResult::offline('status deadline exceeded');
        }
        $socket = @stream_socket_client('udp://'.$endpoint, $errno, $errstr, $deadline->remaining());
        if ($socket === false) {
            return StatusResult::offline('connect: '.($errstr ?: 'failed'));
        }
        try {
            if (! $deadline->write($socket, "\x01".pack('J', (int) (microtime(true) * 1000)).self::MAGIC.pack('J', random_int(0, PHP_INT_MAX)))) {
                return StatusResult::offline('status deadline exceeded');
            }
            $data = $deadline->read($socket, 4096);
            if ($data === false || strlen($data) < 35 || $data[0] !== "\x1c") {
                return StatusResult::offline('no response');
            }
            $latency = $deadline->elapsedMilliseconds();

            $parts = explode(';', substr($data, 35));
            // MCPE;motd;protocol;version;online;max;serverId;subMotd;gamemode;...
            return new StatusResult(
                true,
                isset($parts[4]) ? (int) $parts[4] : null,
                isset($parts[5]) ? (int) $parts[5] : null,
                isset($parts[3]) ? mb_substr($parts[3], 0, 120) : null,
                isset($parts[1]) ? mb_substr(MotdText::stripCodes($parts[1]), 0, 512) : null,
                $latency,
            );
        } finally {
            fclose($socket);
        }
    }
}
