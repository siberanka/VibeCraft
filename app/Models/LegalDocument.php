<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $legal_policy_id
 * @property int $version
 * @property string $country
 * @property string $subdivision
 * @property string $kind
 * @property string $locale
 * @property string $title
 * @property string $body_markdown
 * @property array<string,string> $trader_snapshot
 * @property string $document_sha256
 * @property Carbon|null $published_at
 * @property-read LegalPolicy $policy
 */
class LegalDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['legal_policy_id', 'version', 'country', 'subdivision', 'kind', 'locale', 'title', 'body_markdown', 'trader_snapshot', 'publisher_id', 'published_at'];

    protected $attributes = ['subdivision' => ''];

    protected function casts(): array
    {
        return ['legal_policy_id' => 'integer', 'version' => 'integer', 'trader_snapshot' => 'array', 'published_at' => 'datetime'];
    }

    public function contentDigest(): string
    {
        $trader = $this->trader_snapshot;
        ksort($trader, SORT_STRING);

        return hash('sha256', json_encode([
            'version' => $this->version, 'country' => $this->country, 'subdivision' => $this->subdivision,
            'kind' => $this->kind, 'locale' => $this->locale, 'title' => $this->title,
            'body_markdown' => $this->body_markdown, 'trader' => $trader,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function booted(): void
    {
        static::creating(static function (self $document): void {
            $document->document_sha256 = $document->contentDigest();
        });
        static::updating(static function (): void {
            throw new LogicException('Published legal documents are immutable.');
        });
        static::deleting(static function (): void {
            throw new LogicException('Published legal documents must remain available.');
        });
    }

    /** @return BelongsTo<LegalPolicy, $this> */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(LegalPolicy::class, 'legal_policy_id');
    }
}
