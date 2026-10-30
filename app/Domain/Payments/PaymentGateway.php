<?php

namespace App\Domain\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface PaymentGateway
{
    public function id(): string;

    public function label(): string;

    /** Enabled by the admin and fully configured. */
    public function isAvailable(): bool;

    /**
     * Can verify this provider's webhooks. Independent of the checkout toggle, so
     * refunds, disputes and late confirmations for payments that were already
     * started still apply after an admin switches the method off.
     */
    public function acceptsWebhooks(): bool;

    /** Starts a hosted payment and returns the response that sends the buyer there. */
    public function begin(Order $order, Payment $payment, Request $request): Response;

    /** @throws InvalidWebhook when the request is not authentic */
    public function parseWebhook(Request $request): VerifiedEvent;

    /**
     * Server-to-server confirmation of a "succeeded" event before money is credited.
     * Returns the event to apply, possibly downgraded to IGNORED/FAILED.
     */
    public function confirm(VerifiedEvent $event): VerifiedEvent;

    public function webhookResponse(bool $accepted): Response;
}
