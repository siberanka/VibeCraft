<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasUlids;

    public const CATEGORIES = ['general', 'payment', 'delivery', 'account', 'report', 'appeal'];

    public const STATUSES = ['open', 'answered', 'customer_reply', 'closed'];

    protected $fillable = ['user_id', 'assigned_to', 'order_id', 'subject', 'category', 'priority', 'status', 'last_activity_at'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }
}
