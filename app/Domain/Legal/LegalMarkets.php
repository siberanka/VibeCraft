<?php

namespace App\Domain\Legal;

use App\Core\Audit\AuditLogger;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class LegalMarkets
{
    public function __construct(private LegalConsumer $consumer, private AuditLogger $audit) {}

    public function save(?LegalMarket $market, User $actor, array $input): LegalMarket
    {
        abort_unless($actor->can('settings.manage'), 403);
        $input['country'] = is_string($input['country'] ?? null) ? strtoupper(trim($input['country'])) : ($input['country'] ?? null);
        $input['subdivision'] = is_string($input['subdivision'] ?? null) ? strtoupper(trim($input['subdivision'])) : ($input['subdivision'] ?? '');
        $data = Validator::make($input, [
            'country' => ['required', 'string', Rule::in(array_keys(CountryCatalog::options('en')))],
            'subdivision' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9-]*$/D'],
            'locales' => ['required', 'array', 'list', 'min:1', 'max:10'],
            'locales.*' => ['required', 'string', 'distinct', Rule::in(config('vibecraft.locales'))],
            'trader_name' => ['nullable', 'string', 'max:200'],
            'trader_address' => ['nullable', 'string', 'max:1000'],
            'trader_email' => ['nullable', 'string', 'email:rfc', 'max:191'],
            'trader_phone' => ['nullable', 'string', 'max:40'],
            'trader_registration' => ['nullable', 'string', 'max:100'],
            'trader_tax_id' => ['nullable', 'string', 'max:100'],
            'revision' => [$market ? 'required' : 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();
        try {
            return DB::transaction(function () use ($market, $actor, $data) {
                $actor = LegalStaff::lock($actor);
                $record = $market ? LegalMarket::whereKey($market->id)->lockForUpdate()->firstOrFail() : new LegalMarket;
                if ($record->exists && $record->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
                }
                $subdivision = $data['subdivision'] ?? '';
                if ($record->exists && ($record->country !== $data['country'] || $record->subdivision !== $subdivision)
                    && LegalPolicy::where('legal_market_id', $record->id)->whereHas('documents')->exists()) {
                    throw ValidationException::withMessages(['country' => __('Create a new market instead of moving published legal documents to another country.')]);
                }
                $trader = [];
                foreach (['name', 'address', 'email', 'phone', 'registration', 'tax_id'] as $field) {
                    $trader[$field] = trim($data['trader_'.$field] ?? '');
                }
                $locales = $data['locales'];
                sort($locales, SORT_STRING);
                $record->fill(['country' => $data['country'], 'subdivision' => $subdivision, 'trader' => $trader, 'locales' => $locales])->forceFill([
                    'revision' => $record->exists ? $record->revision + 1 : 1,
                    'is_enabled' => false, 'reviewed_at' => null, 'reviewer_id' => null,
                ])->save();
                DB::afterCommit(fn () => $this->audit->log('legal.market.draft_saved', $record, ['revision' => $record->revision, 'country' => $record->country], $actor->id));

                return $record;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['country' => __('A profile already exists for this country and subdivision.')]);
        }
    }

    /** Operator confirmation records a review; it is not automatic approval of the document's legal merits. */
    public function enable(LegalMarket $market, User $actor, int $expectedRevision, bool $confirmed): LegalMarket
    {
        abort_unless($actor->can('settings.manage'), 403);
        Validator::make(['review_confirmation' => $confirmed], ['review_confirmation' => ['accepted']])->validate();

        return DB::transaction(function () use ($market, $actor, $expectedRevision) {
            $actor = LegalStaff::lock($actor);
            $record = LegalMarket::whereKey($market->id)->lockForUpdate()->firstOrFail();
            if ($record->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
            }
            if ($record->locales === []) {
                throw ValidationException::withMessages(['locales' => __('Choose at least one document language.')]);
            }
            foreach ($record->locales as $locale) {
                $this->consumer->documents($record, $locale, 'checkout', true);
            }
            $record->forceFill(['is_enabled' => true, 'reviewed_at' => now(), 'reviewer_id' => $actor->id, 'revision' => $record->revision + 1])->save();
            DB::afterCommit(fn () => $this->audit->log('legal.market.enabled', $record, ['revision' => $record->revision, 'country' => $record->country], $actor->id));

            return $record;
        }, 5);
    }
}
