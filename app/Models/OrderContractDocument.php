<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $order_contract_id
 * @property int $legal_acceptance_id
 * @property int $legal_document_id
 * @property string $kind
 * @property string $document_sha256
 */
class OrderContractDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_contract_id', 'legal_acceptance_id'];

    protected function casts(): array
    {
        return ['order_contract_id' => 'integer', 'legal_acceptance_id' => 'integer', 'legal_document_id' => 'integer'];
    }

    /** @return BelongsTo<LegalDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    /** @return BelongsTo<LegalAcceptance, $this> */
    public function acceptance(): BelongsTo
    {
        return $this->belongsTo(LegalAcceptance::class, 'legal_acceptance_id');
    }

    protected static function booted(): void
    {
        static::creating(static function (self $link): void {
            if ($link->getConnection()->transactionLevel() < 1) {
                throw new LogicException('Contract documents share the business transaction.');
            }
            $contract = OrderContract::on($link->getConnectionName())->findOrFail($link->order_contract_id);
            $record = LegalAcceptance::on($link->getConnectionName())->with('document')->findOrFail($link->legal_acceptance_id);
            $document = $record->document;
            if ($record->order_id !== $contract->order_id || $record->context !== $contract->context
                || (int) $record->user_id !== $contract->snapshot['review']['buyer_id']
                || ! hash_equals($record->document_sha256, $document->contentDigest())
                || ! hash_equals($document->document_sha256, $record->document_sha256)
                || $record->action !== (in_array($document->kind, ['terms', 'sales'], true) ? 'accepted' : 'presented')
                || ! hash_equals($contract->snapshot_sha256, $contract->contentDigest())) {
                throw new LogicException('Contract document does not belong to its recorded order and customer.');
            }
            $link->legal_document_id = $document->id;
            $link->kind = $document->kind;
            $link->document_sha256 = $document->document_sha256;
        });
        static::updating(static function (): void {
            throw new LogicException('Contract document references are immutable.');
        });
        static::deleting(static function (): void {
            throw new LogicException('Contract document references require a reviewed retention process.');
        });
    }
}
