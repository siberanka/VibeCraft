<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $legal_market_id
 * @property string $kind
 * @property string $locale
 * @property string $title
 * @property string $body_markdown
 * @property int $revision
 * @property int|null $current_document_id
 * @property-read LegalMarket $market
 * @property-read LegalDocument|null $currentDocument
 */
class LegalPolicy extends Model
{
    protected $fillable = ['legal_market_id', 'kind', 'locale', 'title', 'body_markdown'];

    protected $attributes = ['revision' => 1];

    protected function casts(): array
    {
        return ['legal_market_id' => 'integer', 'revision' => 'integer', 'current_document_id' => 'integer'];
    }

    /** @return BelongsTo<LegalMarket, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(LegalMarket::class, 'legal_market_id');
    }

    /** @return BelongsTo<LegalDocument, $this> */
    public function currentDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'current_document_id');
    }

    /** @return HasMany<LegalDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class);
    }
}
