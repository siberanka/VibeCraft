<?php

namespace App\Domain\Commerce;

use App\Core\Audit\AuditLogger;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPublisher;
use App\Models\Order;
use App\Models\OrderContract;
use App\Models\OrderContractNotice;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class OrderContracts
{
    public function __construct(private LegalConsumer $legal, private AuditLogger $audit) {}

    public function requiredFor(Order $order): bool
    {
        return $this->legal->enforced() || OrderContract::where('order_id', $order->id)->exists();
    }

    /** Original copy stays readable even if later order processing needs manual review. */
    public function original(Order $order): ?OrderContract
    {
        $contract = OrderContract::where('order_id', $order->id)->first();
        if (! $contract || ! hash_equals($contract->snapshot_sha256, $contract->contentDigest())
            || ! hash_equals($contract->receipt_sha256, hash('sha256', $contract->receipt_text))
            || ($contract->snapshot['review']['buyer_id'] ?? null) !== (int) $order->user_id
            || ($contract->snapshot['order_public_id'] ?? null) !== $order->public_id) {
            return null;
        }

        return $contract;
    }

    /** A retry returns the original order; a changed request cannot silently reuse its terms. */
    public function previous(User $buyer, OrderConfirmation $confirmation, string $method, ?string $couponCode = null): ?Order
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Order retries require the customer transaction.');
        }
        $confirmation->assertCustomer($buyer, $confirmation->context);
        $contract = OrderContract::where('confirmation_sha256', $confirmation->referenceSha256)->lockForUpdate()->first();
        if (! $contract) {
            return null;
        }
        $order = Order::whereKey($contract->order_id)->lockForUpdate()->firstOrFail();
        $snapshot = $contract->snapshot;
        $review = $snapshot['review'];
        $submittedIds = $confirmation->submission->documentIds;
        $recordedIds = $contract->documents()->pluck('legal_document_id', 'kind')->all();
        ksort($submittedIds, SORT_STRING);
        ksort($recordedIds, SORT_STRING);
        $code = $couponCode !== null ? mb_strtoupper(trim($couponCode)) : '';
        if ((int) $order->user_id !== (int) $buyer->id || $contract->context !== $confirmation->context
            || ! hash_equals($contract->review_sha256, $confirmation->submission->expectedReviewSha256 ?? '')
            || $order->total_minor !== $confirmation->submission->expectedTotalMinor || $method !== $review['method']
            || $code !== ($review['coupon']['code'] ?? '') || $submittedIds !== $recordedIds
            || $snapshot['country'] !== $confirmation->submission->country || $snapshot['subdivision'] !== $confirmation->submission->subdivision
            || $snapshot['locale'] !== $confirmation->submission->locale || ! $this->validFor($order)) {
            throw ValidationException::withMessages(['expected_review_sha256' => __('This review has already created an order. Review again before creating another one.')]);
        }

        return $order;
    }

    /** Joins account/order/price/legal evidence; callers must keep payment inside this transaction. */
    public function capture(Order $order, User $buyer, OrderReview $review, OrderConfirmation $confirmation): OrderContract
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Order contracts share the order creation transaction.');
        }
        $confirmation->assertCustomer($buyer, $review->context());
        $review->assertMatches($confirmation->submission);
        $buyer = User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $prior = OrderContract::where('confirmation_sha256', $confirmation->referenceSha256)->orWhere('order_id', $order->id)->lockForUpdate()->first();
        if ($prior) {
            if ($prior->order_id !== (int) $order->id || $prior->confirmation_sha256 !== $confirmation->referenceSha256
                || ! hash_equals($prior->review_sha256, $review->sha256) || ! $this->validFor($order)) {
                throw ValidationException::withMessages(['expected_review_sha256' => __('This review has already created an order. Review again before creating another one.')]);
            }

            return $prior;
        }
        if ((int) $order->user_id !== (int) $buyer->id) {
            throw new LogicException('The order belongs to another customer.');
        }
        $this->assertOrderLines($order, $review);
        $records = $this->legal->record($confirmation->submission, $buyer, $review->context(), $order);
        $documents = [];
        $trader = [];
        foreach ($records as $record) {
            $record->load('document');
            $document = $record->document;
            $trader = $document->trader_snapshot;
            $documents[] = ['kind' => $document->kind, 'document_id' => $document->id, 'acceptance_id' => $record->id,
                'version' => $document->version, 'locale' => $document->locale, 'title' => $document->title,
                'body_markdown' => $document->body_markdown, 'document_sha256' => $document->document_sha256,
                'published_at' => $document->published_at?->toIso8601String(), 'action' => $record->action,
                'recorded_at' => $record->recorded_at?->toIso8601String()];
        }
        usort($documents, static fn (array $a, array $b) => strcmp($a['kind'], $b['kind']));
        $contract = OrderContract::create(['order_id' => $order->id, 'confirmation_sha256' => $confirmation->referenceSha256,
            'context' => $review->context(), 'execution_plan' => ExecutionPlans::capture($order, $review),
            'snapshot' => ['format' => 1, 'order_id' => (int) $order->id, 'order_public_id' => $order->public_id,
                'order_created_at' => $order->created_at?->toIso8601String(), 'country' => $confirmation->submission->country,
                'subdivision' => $confirmation->submission->subdivision, 'locale' => $confirmation->submission->locale,
                'review' => $review->payload(), 'trader' => $trader, 'documents' => $documents]]);
        foreach ($records as $record) {
            $contract->documents()->create(['legal_acceptance_id' => $record->id]);
        }
        if (! $this->validFor($order)) {
            throw new LogicException('Stored order contract could not be verified.');
        }
        $notice = OrderContractNotice::create(['order_contract_id' => $contract->id, 'recipient' => $buyer->email]);
        DB::afterCommit(fn () => ContractNotifications::dispatch($notice->id, $notice->message_key));
        DB::afterCommit(fn () => $this->audit->log('order.contract.created', $order, ['sha256' => $contract->snapshot_sha256], $buyer->id));

        return $contract;
    }

    /** Verifies the original contract, including archived versions; never substitutes newer terms. */
    public function validFor(Order $order): bool
    {
        $order = Order::whereKey($order->id)->first();
        if (! $order) {
            return false;
        }
        $contract = OrderContract::where('order_id', $order->id)->with('documents.acceptance.document')->first();
        if (! $contract || ! hash_equals($contract->snapshot_sha256, $contract->contentDigest()) || ! hash_equals($contract->receipt_sha256, hash('sha256', $contract->receipt_text))) {
            return false;
        }
        $snapshot = $contract->snapshot;
        $review = $snapshot['review'] ?? [];
        if (! is_array($review) || ! hash_equals($contract->review_sha256, CanonicalJson::digest($review))
            || ($review['buyer_id'] ?? null) !== (int) $order->user_id || ($snapshot['order_public_id'] ?? null) !== $order->public_id
            || ($review['context'] ?? null) !== $contract->context || ($order->kind === 'purchase' ? 'checkout' : ($order->kind === 'topup' ? 'topup' : null)) !== $contract->context
            || ($review['currency'] ?? null) !== $order->currency || ($review['total_minor'] ?? null) !== $order->total_minor
            || ($review['subtotal_minor'] ?? null) !== $order->subtotal_minor || ($review['discount_minor'] ?? null) !== $order->discount_minor
            || ($review['bonus_minor'] ?? 0) !== (int) $order->bonus_minor
            || ($review['gift_recipient']['id'] ?? null) !== $order->gift_recipient_id
            || (($review['method'] ?? null) !== $order->payment_method && ! ($order->total_minor === 0 && $order->payment_method === 'free'))) {
            return false;
        }
        if (! $this->orderLinesMatch($order, $review) || ! ExecutionPlans::valid($contract, $order)) {
            return false;
        }
        $copies = $snapshot['documents'] ?? [];
        if (! is_array($copies) || count($copies) !== 6 || $contract->documents->count() !== 6) {
            return false;
        }
        $kinds = [];
        foreach ($contract->documents as $link) {
            $record = $link->acceptance;
            $document = $record?->document;
            $matches = array_values(array_filter($copies, static fn ($copy) => is_array($copy) && ($copy['kind'] ?? null) === $link->kind));
            if (! $record || ! $document || count($matches) !== 1 || ! in_array($link->kind, LegalPublisher::KINDS, true)
                || $record->order_id !== (int) $order->id || (int) $record->user_id !== (int) $order->user_id || $record->context !== $contract->context
                || $link->legal_document_id !== $document->id || $link->kind !== $document->kind
                || $record->action !== (in_array($document->kind, ['terms', 'sales'], true) ? 'accepted' : 'presented')) {
                return false;
            }
            $copy = $matches[0];
            if (($copy['document_id'] ?? null) !== $document->id || ($copy['acceptance_id'] ?? null) !== $record->id
                || ($copy['body_markdown'] ?? null) !== $document->body_markdown || ($copy['title'] ?? null) !== $document->title
                || ($copy['version'] ?? null) !== $document->version || ($copy['locale'] ?? null) !== $document->locale
                || ($copy['action'] ?? null) !== $record->action || ($copy['published_at'] ?? null) !== $document->published_at?->toIso8601String()
                || ($copy['recorded_at'] ?? null) !== $record->recorded_at?->toIso8601String()
                || ($snapshot['country'] ?? null) !== $document->country || ($snapshot['subdivision'] ?? null) !== $document->subdivision || ($snapshot['locale'] ?? null) !== $document->locale
                || CanonicalJson::digest($snapshot['trader'] ?? []) !== CanonicalJson::digest($document->trader_snapshot)) {
                return false;
            }
            $digest = $document->contentDigest();
            foreach ([$link->document_sha256, $record->document_sha256, $document->document_sha256, $copy['document_sha256'] ?? ''] as $hash) {
                if (! is_string($hash) || ! hash_equals($digest, $hash)) {
                    return false;
                }
            }
            $kinds[] = $link->kind;
        }
        sort($kinds, SORT_STRING);
        $required = LegalPublisher::KINDS;
        sort($required, SORT_STRING);

        return $kinds === $required;
    }

    private function assertOrderLines(Order $order, OrderReview $review): void
    {
        if (! $this->orderLinesMatch($order, $review->payload())) {
            throw new LogicException('The order items changed after review.');
        }
    }

    private function orderLinesMatch(Order $order, array $review): bool
    {
        $lines = $review['lines'] ?? null;
        if (! is_array($lines) || ! array_is_list($lines)) {
            return false;
        }
        $items = $order->items()->orderBy('product_id')->get();
        if (($review['context'] ?? null) === 'topup') {
            return $items->isEmpty() && $lines === [];
        }
        if (($review['context'] ?? null) !== 'checkout' || $lines === [] || $items->count() !== count($lines)) {
            return false;
        }
        $target = $review['minecraft_target'] ?? null;
        if ($target !== null && ! is_array($target)) {
            return false;
        }
        foreach ($items->values() as $index => $item) {
            $line = $lines[$index];
            if (! is_array($line) || (int) $item->product_id !== ($line['product_id'] ?? null) || $item->product_name !== ($line['name'] ?? null) || $item->quantity !== ($line['quantity'] ?? null)
                || $item->unit_price_minor !== ($line['unit_price_minor'] ?? null) || $item->total_minor !== ($line['total_minor'] ?? null) || $item->duration_days !== ($line['duration_days'] ?? null)
                || $item->player_uuid !== ($target['uuid'] ?? null) || $item->player_name !== ($target['username'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
