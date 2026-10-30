<?php

namespace App\Domain\Minecraft;

use UnexpectedValueException;

final class StatusResult
{
    public function __construct(
        public readonly bool $online,
        public readonly ?int $players = null,
        public readonly ?int $maxPlayers = null,
        public readonly ?string $version = null,
        public readonly ?string $motd = null,
        public readonly ?int $latencyMs = null,
        public readonly ?string $error = null,
    ) {
        foreach ([$players, $maxPlayers] as $count) {
            if ($count !== null && ($count < 0 || $count > 1_000_000)) {
                throw new UnexpectedValueException('Server player count is outside the supported range.');
            }
        }
        if ($latencyMs !== null && ($latencyMs < 0 || $latencyMs > 60_000)) {
            throw new UnexpectedValueException('Server latency is outside the supported range.');
        }
        foreach ([[$version, 120], [$motd, 512], [$error, 255]] as [$text, $limit]) {
            if ($text !== null && (! mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > $limit)) {
                throw new UnexpectedValueException('Server status contains invalid text.');
            }
        }
    }

    public static function offline(string $error): self
    {
        $error = mb_check_encoding($error, 'UTF-8') ? mb_substr($error, 0, 255, 'UTF-8') : 'Invalid server response.';

        return new self(false, error: $error);
    }
}
