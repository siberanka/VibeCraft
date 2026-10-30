<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<array{question: string, answer: string}> $answers
 */
class StaffApplication extends Model
{
    use HasUlids;

    protected $fillable = ['staff_position_id', 'user_id', 'answers', 'status', 'reviewer_id', 'review_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['answers' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<StaffPosition, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(StaffPosition::class, 'staff_position_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
