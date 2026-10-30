<?php

namespace App\Console\Commands;

use App\Domain\Payments\Gateways;
use App\Domain\Payments\VerifiedEvent;
use App\Domain\Payments\WebhookInbox;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RetryWebhooks extends Command
{
    protected $signature = 'vibecraft:webhooks-retry';

    protected $description = 'Re-process stored payment events whose processing failed';

    public function handle(Gateways $gateways, WebhookInbox $inbox): int
    {
        WebhookEvent::query()->whereIn('status', ['received', 'failed'])->where('attempts', '<', 10)->orderBy('id')->limit(100)->get()
            ->each(function (WebhookEvent $record) use ($gateways, $inbox) {
                $gateway = $gateways->get($record->provider);
                $event = WebhookInbox::rebuild($record);
                // @phpstan-ignore instanceof.alwaysTrue (Keep the normalized event boundary explicit.)
                if ($gateway !== null && $event instanceof VerifiedEvent) {
                    $this->line($record->provider.':'.$record->event_id.' → '.$inbox->process($gateway, $record, $event));
                }
            });

        return self::SUCCESS;
    }
}
