<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use LogicException;

/**
 * @property int $id
 * @property int $order_contract_id
 * @property string $recipient
 * @property string $message_key
 * @property string $state
 * @property int $attempts
 * @property string|null $attempt_token
 * @property Carbon|null $locked_at
 */
class OrderContractNotice extends Model
{
    protected $fillable = ['order_contract_id', 'recipient'];

    protected $hidden = ['recipient', 'attempt_token'];

    protected $attributes = ['state' => 'pending', 'attempts' => 0];

    protected function casts(): array
    {
        return ['order_contract_id' => 'integer', 'recipient' => 'encrypted', 'attempts' => 'integer', 'locked_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    /** @return BelongsTo<OrderContract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(OrderContract::class, 'order_contract_id');
    }

    protected static function booted(): void
    {
        static::creating(static function (self $notice): void {
            if ($notice->getConnection()->transactionLevel() < 1) {
                throw new LogicException('Contract notice creation shares its business transaction.');
            }
            Validator::make(['recipient' => $notice->recipient], ['recipient' => ['required', 'string', 'email:rfc', 'max:191']])->validate();
            $contract = OrderContract::on($notice->getConnectionName())->findOrFail($notice->order_contract_id);
            $notice->message_key = hash('sha256', 'contract-notice-v1|'.$contract->confirmation_sha256.'|'.$contract->receipt_sha256);
        });
        static::updating(static function (self $notice): void {
            if ($notice->isDirty(['order_contract_id', 'recipient', 'message_key'])) {
                throw new LogicException('The original contract recipient and message reference are immutable.');
            }
        });
        static::deleting(static function (): void {
            throw new LogicException('Contract notices require a reviewed retention process.');
        });
    }
}
