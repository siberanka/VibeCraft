<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $wiki_article_id
 * @property int $user_id
 * @property int $revision
 * @property bool $helpful
 */
class WikiFeedback extends Model
{
    protected $table = 'wiki_feedback';

    protected $fillable = ['wiki_article_id', 'user_id', 'revision', 'helpful'];

    protected function casts(): array
    {
        return ['wiki_article_id' => 'integer', 'user_id' => 'integer', 'revision' => 'integer', 'helpful' => 'boolean'];
    }
}
