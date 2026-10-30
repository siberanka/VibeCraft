<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $wiki_article_id
 * @property int $revision
 * @property int|null $editor_id
 * @property string $locale
 * @property string $title
 * @property string|null $summary
 * @property string $body_markdown
 * @property list<array{question:string,answer:string}>|null $faqs
 * @property string $origin
 * @property int|null $based_on_revision
 * @property Carbon $created_at
 * @property-read WikiArticle $article
 * @property-read User|null $editor
 */
class WikiRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'wiki_article_id', 'revision', 'editor_id', 'locale', 'title', 'summary',
        'body_markdown', 'origin', 'based_on_revision', 'created_at', 'faqs',
    ];

    protected function casts(): array
    {
        return ['wiki_article_id' => 'integer', 'editor_id' => 'integer', 'revision' => 'integer', 'based_on_revision' => 'integer', 'faqs' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Wiki content revisions are append-only.');
        });
        static::deleting(static function (): void {
            throw new LogicException('Wiki content revisions are append-only.');
        });
    }

    /** @return BelongsTo<WikiArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(WikiArticle::class, 'wiki_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
