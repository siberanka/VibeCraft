<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $legal_document_id
 * @property int|null $user_id
 * @property int|null $order_id
 * @property string $context
 * @property string $action
 * @property string $document_sha256
 * @property Carbon|null $recorded_at
 */
class LegalAcceptance extends Model
{
    public $timestamps = false;

    protected $fillable = ['legal_document_id', 'user_id', 'order_id', 'context', 'action', 'recorded_at'];

    /** @return BelongsTo<LegalDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    protected function casts(): array
    {
        return ['legal_document_id' => 'integer', 'user_id' => 'integer', 'order_id' => 'integer', 'recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $acceptance): void {
            if (! in_array($acceptance->context, ['registration', 'checkout', 'topup'], true)
                || ! in_array($acceptance->action, ['accepted', 'presented'], true)) {
                throw new LogicException('Unknown legal evidence purpose.');
            }
            $document = LegalDocument::on($acceptance->getConnectionName())->findOrFail($acceptance->legal_document_id);
            if (! hash_equals($document->document_sha256, $document->contentDigest())) {
                throw new LogicException('Legal document content does not match its digest.');
            }
            $acceptance->document_sha256 = $document->document_sha256;
        });
        static::updating(static function (): void {
            throw new LogicException('Legal acceptance evidence is append-only.');
        });
        static::deleting(static function (): void {
            throw new LogicException('Legal evidence cannot be deleted through a model instance.');
        });
    }
}
