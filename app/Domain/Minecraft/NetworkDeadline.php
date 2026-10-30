<?php

namespace App\Domain\Minecraft;

/** One monotonic budget for a complete socket exchange, including partial I/O. */
final class NetworkDeadline
{
    private readonly int $started;

    private readonly int $expires;

    public function __construct(float $seconds)
    {
        $seconds = is_finite($seconds) ? max(0.001, min(10.0, $seconds)) : 3.0;
        $this->started = hrtime(true);
        $this->expires = $this->started + (int) ($seconds * 1_000_000_000);
    }

    public function remaining(): float
    {
        return max(0.0, ($this->expires - hrtime(true)) / 1_000_000_000);
    }

    public function elapsedMilliseconds(): int
    {
        return (int) round((hrtime(true) - $this->started) / 1_000_000);
    }

    /** @param resource $stream */
    private function prepare($stream): bool
    {
        $remaining = $this->remaining();
        if ($remaining <= 0) {
            return false;
        }
        $microseconds = max(1, (int) ceil($remaining * 1_000_000));

        return stream_set_timeout($stream, intdiv($microseconds, 1_000_000), $microseconds % 1_000_000);
    }

    /** @param resource $stream */
    public function read($stream, int $length): string|false
    {
        if (! $this->prepare($stream)) {
            return false;
        }
        $data = @fread($stream, $length);
        // @phpstan-ignore nullCoalesce.offset (Keep the conservative stream metadata fallback.)
        if ($this->remaining() <= 0 || (stream_get_meta_data($stream)['timed_out'] ?? false)) {
            return false;
        }

        return $data;
    }

    /** @param resource $stream */
    public function write($stream, string $bytes): bool
    {
        $offset = 0;
        while ($offset < strlen($bytes)) {
            if (! $this->prepare($stream)) {
                return false;
            }
            $written = @fwrite($stream, substr($bytes, $offset));
            if ($written === false || $written === 0 || $this->remaining() <= 0) {
                return false;
            }
            $offset += $written;
        }

        return true;
    }
}
