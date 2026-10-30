<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Short in-site notice for a player. Only a translation key, a few scalar
 * parameters and an internal path are stored; never payment or personal data.
 */
class PlayerNotice extends Notification
{
    public const KINDS = ['order_paid', 'ticket_answered', 'application_accepted', 'application_rejected', 'gift_received'];

    /** @param array<string, string> $params */
    public function __construct(public string $kind, public array $params = [], public string $path = '/') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => in_array($this->kind, self::KINDS, true) ? $this->kind : 'order_paid',
            'params' => array_map(fn ($v) => mb_substr((string) $v, 0, 120), $this->params),
            'path' => str_starts_with($this->path, '/') && ! str_starts_with($this->path, '//') ? $this->path : '/',
        ];
    }
}
