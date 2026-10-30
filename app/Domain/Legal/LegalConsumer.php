<?php

namespace App\Domain\Legal;

use App\Core\Settings\Settings;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class LegalConsumer
{
    public function __construct(private Settings $settings, private LegalPublisher $publisher) {}

    /** Real installations require policies; only the explicit isolated test profile may use legacy fixtures. */
    public function enforced(): bool
    {
        return ! app()->environment('testing') || $this->settings->bool('legal.enforce');
    }

    public static function kinds(string $context): array
    {
        return match ($context) {
            'registration' => ['terms', 'privacy', 'cookies'],
            'checkout', 'topup' => LegalPublisher::KINDS,
            default => throw new LogicException('Unknown legal agreement context.'),
        };
    }

    /** This structural check is shared with the staff activation step, not a legal certification. */
    public function documents(LegalMarket $market, string $locale, string $context, bool $lock = false): array
    {
        if (! CountryCatalog::contains($market->country) || ! in_array($locale, $market->locales, true)) {
            $this->unavailable();
        }
        $required = self::kinds($context);
        $query = LegalPolicy::where('legal_market_id', $market->id)->where('locale', $locale)->whereIn('kind', $required)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $policies = $query->get()->keyBy('kind');
        $documentQuery = LegalDocument::whereIn('id', $policies->pluck('current_document_id')->filter()->all())->orderBy('id');
        if ($lock) {
            // A locking read sees the same committed versions as the locked policy pointers.
            $documentQuery->sharedLock();
        }
        $published = $documentQuery->get()->keyBy('id');
        $trader = $this->publisher->trader($market);
        $documents = [];
        foreach ($required as $kind) {
            $policy = $policies->get($kind);
            $document = $policy ? $published->get($policy->current_document_id) : null;
            if (! $document || $document->legal_policy_id !== $policy->id || $document->kind !== $kind
                || $document->locale !== $locale || $document->country !== $market->country || $document->subdivision !== $market->subdivision
                || $document->published_at === null || $document->published_at->isFuture()) {
                $this->unavailable();
            }
            $snapshot = $document->trader_snapshot;
            ksort($snapshot, SORT_STRING);
            if ($snapshot !== $trader || ! hash_equals($document->document_sha256, $document->contentDigest())) {
                $this->unavailable();
            }
            $documents[$kind] = $document;
        }

        return $documents;
    }

    /** Re-resolves the selected country and exact displayed versions; no locale/currency inference. */
    public function resolve(string $country, string $subdivision, string $locale, string $context, bool $lock = false): array
    {
        $query = LegalMarket::where('country', $country)->where('subdivision', $subdivision);
        if ($lock) {
            $query->lockForUpdate();
        }
        $market = $query->first();
        if (! $market || ! $market->is_enabled || $market->reviewed_at === null) {
            $this->unavailable();
        }
        try {
            $documents = $this->documents($market, $locale, $context, $lock);
        } catch (ValidationException) {
            $this->unavailable();
        }

        return ['market' => $market, 'documents' => $documents];
    }

    /** Must run before payment/fulfilment within the account or order creation transaction. */
    public function record(LegalSubmission $submission, User $user, string $context, ?Order $order = null): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Legal evidence must share the business transaction.');
        }
        $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
        if ($order !== null) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        }
        if ($user->isBanned()) {
            throw ValidationException::withMessages(['terms' => __('This account is suspended.')]);
        }
        if ($context === 'registration') {
            if ($order !== null) {
                throw new LogicException('Registration evidence cannot identify a purchase.');
            }
        } else {
            if (! $order || (int) $order->user_id !== $user->id || $order->status !== Order::PENDING
                || ($context === 'checkout' && $order->kind !== 'purchase') || ($context === 'topup' && $order->kind !== 'topup')) {
                throw new LogicException('Order legal evidence must precede payment and belong to its customer.');
            }
            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['terms' => __('Verify your email before confirming a purchase.')]);
            }
            if ($submission->expectedTotalMinor !== $order->total_minor) {
                throw ValidationException::withMessages(['expected_total_minor' => __('The total changed. Review the order before confirming it.')]);
            }
        }
        app(LegalScope::class)->assertSubmission($submission);
        $selection = $this->resolve($submission->country, $submission->subdivision, $submission->locale, $context, true);
        $ids = array_map(static fn (LegalDocument $document) => $document->id, $selection['documents']);
        $submitted = $submission->documentIds;
        ksort($ids, SORT_STRING);
        ksort($submitted, SORT_STRING);
        if ($ids !== $submitted) {
            throw ValidationException::withMessages(['legal_document_ids' => __('Legal documents changed. Review the current versions before continuing.')]);
        }
        if ($order) {
            $existing = LegalAcceptance::where('order_id', $order->id)->where('context', $context)->get();
            if ($existing->isNotEmpty()) {
                $recorded = $existing->pluck('legal_document_id')->sort()->values()->all();
                $selected = array_values($ids);
                sort($selected, SORT_NUMERIC);
                if ($recorded !== $selected || $existing->contains(fn (LegalAcceptance $record) => $record->user_id !== $user->id)) {
                    throw ValidationException::withMessages(['legal_document_ids' => __('This order already has a contract. Create a new order to use different terms.')]);
                }
                $byId = [];
                foreach ($selection['documents'] as $document) {
                    $byId[$document->id] = $document;
                }
                foreach ($existing as $record) {
                    $document = $byId[$record->legal_document_id];
                    $action = in_array($document->kind, ['terms', 'sales'], true) ? 'accepted' : 'presented';
                    if ($record->action !== $action || ! hash_equals($record->document_sha256, $document->document_sha256)) {
                        throw new LogicException('Stored order agreement is inconsistent.');
                    }
                }

                return $existing->all();
            }
        }
        $records = [];
        foreach ($selection['documents'] as $kind => $document) {
            $record = LegalAcceptance::firstOrCreate([
                'legal_document_id' => $document->id, 'user_id' => $user->id, 'order_id' => $order?->id,
                'context' => $context, 'action' => in_array($kind, ['terms', 'sales'], true) ? 'accepted' : 'presented',
            ], ['recorded_at' => now()]);
            if (! hash_equals($record->document_sha256, $document->document_sha256)) {
                throw new LogicException('Stored legal evidence is inconsistent.');
            }
            $records[] = $record;
        }

        return $records;
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['legal_country' => __('Legal information for this market is not ready yet.')]);
    }
}
