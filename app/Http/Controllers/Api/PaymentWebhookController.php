<?php

namespace App\Http\Controllers\Api;

use App\Domain\Payments\Gateways;
use App\Domain\Payments\InvalidWebhook;
use App\Domain\Payments\WebhookInbox;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, Gateways $gateways, WebhookInbox $inbox): Response
    {
        $gateway = $gateways->get($provider);
        if ($gateway === null || ! $gateway->acceptsWebhooks()) {
            abort(404);
        }

        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidWebhook $e) {
            Log::notice('webhook.rejected', ['provider' => $provider, 'reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return $gateway->webhookResponse(false);
        }

        $result = $inbox->accept($gateway, $event);

        // The event is durably stored; a processing failure is retried by the scheduler,
        // so the provider still receives a success acknowledgement.
        $response = $gateway->webhookResponse(true);
        $response->headers->set('X-VC-Webhook', $result);

        return $response;
    }
}
