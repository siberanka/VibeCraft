<?php

namespace App\Models;

use App\Domain\Commerce\ContractText;
use App\Support\CanonicalJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $order_id
 * @property string $context
 * @property string $confirmation_sha256
 * @property string $review_sha256
 * @property string $snapshot_sha256
 * @property string $receipt_text
 * @property string $receipt_sha256
 * @property array $snapshot
 * @property array $execution_plan
 * @property string $execution_sha256
 * @property Carbon|null $recorded_at
 */
class OrderContract extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'confirmation_sha256', 'context', 'snapshot', 'execution_plan'];

    protected $hidden = ['execution_plan'];

    protected function casts(): array
    {
        return ['order_id' => 'integer', 'snapshot' => 'array', 'execution_plan' => 'encrypted:array', 'recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<OrderContractDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(OrderContractDocument::class);
    }

    public function contentDigest(): string
    {
        return CanonicalJson::digest(['format' => 1, 'order_id' => $this->order_id, 'context' => $this->context,
            'confirmation_sha256' => $this->confirmation_sha256, 'review_sha256' => $this->review_sha256,
            'receipt_sha256' => $this->receipt_sha256,
            'execution_sha256' => $this->execution_sha256,
            'recorded_at' => $this->recorded_at?->copy()->utc()->toIso8601String(), 'snapshot' => $this->snapshot]);
    }

    protected static function booted(): void
    {
        static::creating(static function (self $contract): void {
            if ($contract->getConnection()->transactionLevel() < 1 || ! preg_match('/^[a-f0-9]{64}$/D', $contract->confirmation_sha256)) {
                throw new LogicException('An order contract requires the business transaction and confirmation reference.');
            }
            $order = Order::on($contract->getConnectionName())->findOrFail($contract->order_id);
            $snapshot = $contract->snapshot;
            $review = $snapshot['review'] ?? [];
            $context = $order->kind === 'purchase' ? 'checkout' : ($order->kind === 'topup' ? 'topup' : null);
            if ($order->status !== Order::PENDING || $context === null || $contract->context !== $context || ($snapshot['format'] ?? null) !== 1
                || ($snapshot['order_id'] ?? null) !== (int) $order->id || ($snapshot['order_public_id'] ?? null) !== $order->public_id
                || ! is_array($review) || ($review['context'] ?? null) !== $context || ($review['buyer_id'] ?? null) !== (int) $order->user_id
                || ($review['currency'] ?? null) !== $order->currency || ($review['method'] ?? null) !== $order->payment_method
                || ($review['total_minor'] ?? null) !== $order->total_minor || ($review['subtotal_minor'] ?? null) !== $order->subtotal_minor
                || ($review['discount_minor'] ?? null) !== $order->discount_minor) {
                throw new LogicException('The order contract does not match its unpaid order.');
            }
            if (strlen(CanonicalJson::encode($snapshot)) > 8_000_000) {
                throw new LogicException('Order contract exceeds the storage bound.');
            }
            $contract->recorded_at = now()->startOfSecond();
            $contract->execution_sha256 = CanonicalJson::digest($contract->execution_plan);
            $contract->review_sha256 = CanonicalJson::digest($review);
            $contract->receipt_text = ContractText::render($snapshot, $contract->recorded_at->copy()->utc()->toIso8601String());
            $contract->receipt_sha256 = hash('sha256', $contract->receipt_text);
            $contract->snapshot_sha256 = $contract->contentDigest();
        });
        static::updating(static function (): void {
            throw new LogicException('Order contracts are immutable.');
        });
        static::deleting(static function (): void {
            throw new LogicException('Order contracts require a reviewed retention process.');
        });
    }
}
