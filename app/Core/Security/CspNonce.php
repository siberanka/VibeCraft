<?php

namespace App\Core\Security;

final class CspNonce
{
    private ?string $nonce = null;

    public function get(): string
    {
        return $this->nonce ??= base64_encode(random_bytes(18));
    }
}
