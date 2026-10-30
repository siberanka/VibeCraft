<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $payload
 */
class WebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'event_type', 'payload', 'status', 'attempts', 'error', 'processed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
