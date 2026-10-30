<?php

namespace App\Domain\Minecraft;

use App\Core\Security\PublicEndpoint;

/**
 * Minimal Java Edition Server List Ping (protocol handshake + status request).
 * Reads are bounded so a hostile server cannot exhaust memory.
 */
class JavaPinger
{
    private const MAX_RESPONSE = 65536;

    public function ping(string $host, int $port, float $timeout = 3.0): StatusResult
    {
        $deadline = new NetworkDeadline($timeout);
        [$host, $port] = $this->resolveSrv($host, $port);
        $endpoint = PublicEndpoint::address($host, $port);
        // PHP's OS resolver is blocking; charge its elapsed time to this budget.
        if ($deadline->remaining() <= 0) {
            return StatusResult::offline('status deadline exceeded');
        }
        $socket = @stream_socket_client('tcp://'.$endpoint, $errno, $errstr, $deadline->remaining());
        if ($socket === false) {
            return StatusResult::offline('connect: '.($errstr ?: 'failed'));
        }
        try {
            $handshake = "\x00".self::varInt(-1).self::string($host).pack('n', $port).self::varInt(1);
            if (! $deadline->write($socket, self::varInt(strlen($handshake)).$handshake."\x01\x00")) {
                return StatusResult::offline('status deadline exceeded');
            }

            $length = $this->readVarInt($socket, $deadline);
            if ($length < 1 || $length > self::MAX_RESPONSE) {
                return StatusResult::offline('bad length');
            }
            $packetId = $this->readVarInt($socket, $deadline);
            if ($packetId !== 0) {
                return StatusResult::offline('bad packet');
            }
            $jsonLength = $this->readVarInt($socket, $deadline);
            if ($jsonLength < 2 || $jsonLength > self::MAX_RESPONSE) {
                return StatusResult::offline('bad json length');
            }

            $json = '';
            while (strlen($json) < $jsonLength) {
                $chunk = $deadline->read($socket, $jsonLength - strlen($json));
                if ($chunk === false || $chunk === '') {
                    return StatusResult::offline('truncated');
                }
                $json .= $chunk;
            }
            $latency = $deadline->elapsedMilliseconds();

            $data = json_decode($json, true, 32);
            if (! is_array($data)) {
                return StatusResult::offline('invalid json');
            }

            return new StatusResult(
                true,
                isset($data['players']['online']) ? (int) $data['players']['online'] : null,
                isset($data['players']['max']) ? (int) $data['players']['max'] : null,
                isset($data['version']['name']) ? mb_substr((string) $data['version']['name'], 0, 120) : null,
                mb_substr(MotdText::plain($data['description'] ?? ''), 0, 512),
                $latency,
            );
        } finally {
            fclose($socket);
        }
    }

    /** @return array{0: string, 1: int} */
    private function resolveSrv(string $host, int $port): array
    {
        if ($port === 25565 && ! filter_var($host, FILTER_VALIDATE_IP) && function_exists('dns_get_record')) {
            $records = @dns_get_record('_minecraft._tcp.'.$host, DNS_SRV);
            if (is_array($records) && isset($records[0]['target'], $records[0]['port'])) {
                return [rtrim((string) $records[0]['target'], '.'), (int) $records[0]['port']];
            }
        }

        return [$host, $port];
    }

    /** @param resource $socket */
    private function readVarInt($socket, NetworkDeadline $deadline): int
    {
        $value = 0;
        for ($i = 0; $i < 5; $i++) {
            $byte = $deadline->read($socket, 1);
            if ($byte === false || $byte === '') {
                return -1;
            }
            $b = ord($byte);
            $value |= ($b & 0x7F) << (7 * $i);
            if (($b & 0x80) === 0) {
                return $value;
            }
        }

        return -1;
    }

    private static function varInt(int $value): string
    {
        $value &= 0xFFFFFFFF;
        $out = '';
        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $out .= chr($value !== 0 ? $byte | 0x80 : $byte);
        } while ($value !== 0);

        return $out;
    }

    private static function string(string $s): string
    {
        return self::varInt(strlen($s)).$s;
    }
}
