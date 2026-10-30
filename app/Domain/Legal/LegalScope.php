<?php

namespace App\Domain\Legal;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Settings;
use App\Models\LegalMarket;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Operator scope is explicit configuration, never a residence inferred from language. */
final class LegalScope
{
    public function __construct(private Settings $settings, private LegalConsumer $consumer, private AuditLogger $audit) {}

    public function customerChooses(): bool
    {
        return $this->settings->get('legal.market_selection') === 'customer';
    }

    public function defaultMarket(): ?LegalMarket
    {
        $id = (int) $this->settings->get('legal.default_market_id');

        return $this->marketForId($id);
    }

    private function marketForId(int $id, bool $lock = false): ?LegalMarket
    {
        $query = LegalMarket::where('is_enabled', true)->whereNotNull('reviewed_at');
        if ($lock) {
            $query->lockForUpdate();
        }
        if ($id > 0) {
            return $query->whereKey($id)->first();
        }
        // Unambiguous across all languages; changing language cannot switch jurisdictions.
        $markets = $query->orderBy('id')->limit(2)->get();

        return $markets->count() === 1 ? $markets->first() : null;
    }

    /** A current locking read keeps an old form from bypassing a changed operator configuration. */
    public function assertSubmission(LegalSubmission $submission): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Document scope checks require the business transaction.');
        }
        $rows = Setting::whereIn('key', ['legal.market_selection', 'legal.default_market_id', 'legal.selection_revision'])->orderBy('key')->lockForUpdate()->get()->keyBy('key');
        $values = [];
        foreach ($rows as $key => $row) {
            $values[$key] = json_decode((string) $row->getAttribute('value'), true, 8, JSON_THROW_ON_ERROR);
        }
        if ($submission->scopeRevision !== (int) ($values['legal.selection_revision'] ?? 1)) {
            throw ValidationException::withMessages(['legal_document_ids' => __('Document settings changed. Review the current form before continuing.')]);
        }
        if (($values['legal.market_selection'] ?? 'operator') !== 'customer') {
            $market = $this->marketForId((int) ($values['legal.default_market_id'] ?? 0), true);
            if (! $market || $market->country !== $submission->country || $market->subdivision !== $submission->subdivision) {
                throw ValidationException::withMessages(['legal_country' => __('The configured document profile is not available for this request.')]);
            }
        }
    }

    public function configure(User $actor, array $input): void
    {
        abort_unless($actor->can('settings.manage'), 403);
        $data = Validator::make($input, ['selection_mode' => ['required', Rule::in(['operator', 'customer'])],
            'default_market_id' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'revision' => ['required', 'integer', 'min:1', 'max:2147483647']])->validate();
        DB::transaction(function () use ($actor, $data) {
            $actor = LegalStaff::lock($actor);
            $revision = Setting::query()->lockForUpdate()->firstOrCreate(['key' => 'legal.selection_revision'], ['value' => '1', 'is_encrypted' => false]);
            $current = (int) json_decode((string) $revision->getAttribute('value'), true, 8, JSON_THROW_ON_ERROR);
            if ($current !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
            }
            $marketId = (int) ($data['default_market_id'] ?? 0);
            if ($marketId > 0) {
                $market = LegalMarket::whereKey($marketId)->lockForUpdate()->first();
                if (! $market || ! $market->is_enabled || $market->reviewed_at === null) {
                    throw ValidationException::withMessages(['default_market_id' => __('Choose an enabled and reviewed document profile.')]);
                }
                foreach ($market->locales as $locale) {
                    $this->consumer->documents($market, $locale, 'checkout', true);
                }
            }
            $this->settings->setMany(['legal.market_selection' => $data['selection_mode'], 'legal.default_market_id' => $marketId, 'legal.selection_revision' => $current + 1]);
            DB::afterCommit(function () use ($actor, $data, $marketId, $current) {
                $this->settings->flush();
                $this->audit->log('legal.scope.updated', null, ['mode' => $data['selection_mode'], 'market_id' => $marketId, 'revision' => $current + 1], $actor->id);
            });
        }, 5);
    }
}
