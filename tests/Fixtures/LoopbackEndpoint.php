<?php

namespace Tests\Fixtures;

/** Explicitly aliased only by the guarded, standalone Linux QA harness. */
final class LoopbackEndpoint
{
    public static function address(string $host, int $port): string
    {
        if ($host !== '127.0.0.1' || $port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('The fixture only accepts its isolated local peer.');
        }

        return '127.0.0.1:'.$port;
    }
}
