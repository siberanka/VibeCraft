<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPost extends Model
{
    protected $fillable = ['forum_thread_id', 'user_id', 'body_markdown', 'body_html', 'hidden', 'hidden_by'];

    protected function casts(): array
    {
        return ['hidden' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ForumThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'forum_thread_id');
    }
}
