<?php

namespace App\Domain\Payments;

/** A provider event whose authenticity has been verified. */
final class VerifiedEvent
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

    public const DISPUTED = 'disputed';

    public const IGNORED = 'ignored';

    /** @param array<string, mixed> $payload redacted payload stored in the inbox */
    public function __construct(
        public readonly string $provider,
        public readonly string $eventId,
        public readonly string $type,
        public readonly string $outcome,
        public readonly ?string $reference = null,
        public readonly ?string $providerPaymentId = null,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly array $payload = [],
    ) {
        if (! preg_match('/^[a-z][a-z0-9_-]{0,19}$/D', $provider) || $eventId === '' || strlen($eventId) > 191
            || $type === '' || strlen($type) > 80 || ! in_array($outcome, [self::SUCCEEDED, self::FAILED, self::REFUNDED, self::DISPUTED, self::IGNORED], true)
            || ($reference !== null && ($reference === '' || strlen($reference) > 191))
            || ($providerPaymentId !== null && ($providerPaymentId === '' || strlen($providerPaymentId) > 191))
            || ($amountMinor !== null && ($amountMinor < 0 || $amountMinor > 9_000_000_000_000))
            || ($currency !== null && ! preg_match('/^[A-Z]{3}$/D', $currency))
            || ($outcome === self::SUCCEEDED && ($reference === null || $providerPaymentId === null || $amountMinor === null || $currency === null))) {
            throw new InvalidWebhook('invalid normalized event');
        }
    }
}
