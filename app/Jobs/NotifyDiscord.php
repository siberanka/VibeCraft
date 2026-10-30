<?php

namespace App\Jobs;

use App\Core\Settings\Settings;
use App\Models\Order;
use App\Models\Ticket;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Posts a short notification to a Discord webhook. The URL is validated against
 * Discord hosts when saved (no SSRF) and mentions are disabled.
 */
class NotifyDiscord implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $kind, public int $id) {}

    public static function isValidWebhookUrl(string $url): bool
    {
        return (bool) preg_match('#^https://(?:(?:canary|ptb)\.)?discord(?:app)?\.com/api/webhooks/\d{5,30}/[A-Za-z0-9_-]{20,120}$#', $url);
    }

    public function handle(Settings $settings): void
    {
        $url = (string) $settings->secret('discord.webhook_url');
        if ($url === '' || ! self::isValidWebhookUrl($url)) {
            return;
        }

        $content = match ($this->kind) {
            'order' => $this->orderMessage($settings),
            'ticket' => $this->ticketMessage($settings),
            default => null,
        };
        if ($content === null) {
            return;
        }

        Http::timeout(10)->post($url, [
            'content' => mb_substr($content, 0, 1900),
            'allowed_mentions' => ['parse' => []],
        ])->throw();
    }

    private function orderMessage(Settings $settings): ?string
    {
        $order = Order::query()->with('items')->find($this->id);
        if ($order === null || ! $settings->bool('discord.notify_orders')) {
            return null;
        }
        $player = $order->items->first()?->player_name;
        $what = $order->kind === 'topup' ? __('balance top-up') : $order->items->pluck('product_name')->implode(', ');

        return '🛒 '.self::escape(($player ?? __('A player')).' — '.$what.' ('.Money::format($order->total_minor, $order->currency).')');
    }

    private function ticketMessage(Settings $settings): ?string
    {
        $ticket = Ticket::query()->find($this->id);
        if ($ticket === null || ! $settings->bool('discord.notify_tickets')) {
            return null;
        }

        return '🎫 '.self::escape(__('New support ticket').': '.$ticket->subject);
    }

    private static function escape(string $text): string
    {
        return preg_replace('/([\\\\*_`~|>@#\[\]()])/', '\\\\$1', $text) ?? '';
    }
}
