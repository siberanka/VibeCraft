<?php

namespace App\Domain\Legal;

use App\Core\Audit\AuditLogger;
use App\Domain\Content\WikiRenderer;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

final class LegalPublisher
{
    public const KINDS = ['terms', 'privacy', 'cookies', 'sales', 'precontract', 'withdrawal'];

    public function __construct(private AuditLogger $audit) {}

    /** Saves a draft; publishing and changing the public contract are separate actions. */
    public function saveDraft(LegalMarket $market, User $actor, array $input): LegalPolicy
    {
        abort_unless($actor->can('settings.manage'), 403);
        $data = Validator::make($input, [
            'kind' => ['required', Rule::in(self::KINDS)],
            'locale' => ['required', 'string', Rule::in(config('vibecraft.locales'))],
            'title' => ['required', 'string', 'max:160'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'revision' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        return DB::transaction(function () use ($market, $actor, $data) {
            $actor = LegalStaff::lock($actor);
            $currentMarket = LegalMarket::whereKey($market->id)->lockForUpdate()->firstOrFail();
            if (! in_array($data['locale'], $currentMarket->locales, true)) {
                throw ValidationException::withMessages(['locale' => __('Choose a language enabled for this market.')]);
            }
            $policy = LegalPolicy::where('legal_market_id', $currentMarket->id)->where('kind', $data['kind'])->where('locale', $data['locale'])->lockForUpdate()->first();
            if ($policy && $policy->revision !== (int) ($data['revision'] ?? 0)) {
                throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
            }
            $policy ??= new LegalPolicy;
            $policy->fill([
                'legal_market_id' => $currentMarket->id, 'kind' => $data['kind'], 'locale' => $data['locale'],
                'title' => $data['title'], 'body_markdown' => $data['body_markdown'],
            ])->forceFill(['revision' => $policy->exists ? $policy->revision + 1 : 1])->save();
            DB::afterCommit(fn () => $this->audit->log('legal.policy.draft_saved', $policy, ['revision' => $policy->revision], $actor->id));

            return $policy;
        }, 5);
    }

    /** Requires the reviewed draft version and records the actual published trader snapshot. */
    public function publish(LegalPolicy $policy, int $expectedRevision, User $actor): LegalDocument
    {
        abort_unless($actor->can('settings.manage'), 403);

        return DB::transaction(function () use ($policy, $expectedRevision, $actor) {
            $actor = LegalStaff::lock($actor);
            $market = LegalMarket::whereKey($policy->legal_market_id)->lockForUpdate()->firstOrFail();
            $current = LegalPolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($current->legal_market_id !== $market->id || $current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => __('Another editor updated this content. Reload the page before saving.')]);
            }
            Validator::make($current->only(['title', 'body_markdown']), [
                'title' => ['required', 'string', 'max:160'],
                'body_markdown' => ['required', 'string', 'max:100000'],
            ])->validate();
            $trader = $this->trader($market);
            if (! in_array($current->locale, $market->locales, true) || ! in_array($current->kind, self::KINDS, true)) {
                throw ValidationException::withMessages(['locale' => __('Choose a language enabled for this market.')]);
            }
            if (preg_match('/\{\{[^}]*\}\}|\[\[[^\]]*\]\]|\b(?:TODO|TBD)\b/iu', $current->title."\n".$current->body_markdown."\n".implode("\n", $trader))) {
                throw ValidationException::withMessages(['body_markdown' => __('Replace all draft placeholders before publishing.')]);
            }
            WikiRenderer::render($current->body_markdown, $current->locale);
            $previous = $current->currentDocument;
            if ($previous) {
                if ($previous->legal_policy_id !== $current->id || ! hash_equals($previous->document_sha256, $previous->contentDigest())) {
                    throw new LogicException('Current legal publication is inconsistent.');
                }
                $previousTrader = $previous->trader_snapshot;
                ksort($previousTrader, SORT_STRING);
                if ($previous->title === $current->title && $previous->body_markdown === $current->body_markdown
                    && $previous->kind === $current->kind && $previous->locale === $current->locale
                    && $previousTrader === $trader && $previous->country === $market->country && $previous->subdivision === $market->subdivision) {
                    return $previous;
                }
            }
            $version = (int) $current->documents()->max('version') + 1;
            $document = LegalDocument::create([
                'legal_policy_id' => $current->id, 'version' => $version, 'country' => $market->country,
                'subdivision' => $market->subdivision, 'kind' => $current->kind, 'locale' => $current->locale,
                'title' => $current->title, 'body_markdown' => $current->body_markdown,
                'trader_snapshot' => $trader, 'publisher_id' => $actor->id, 'published_at' => now(),
            ]);
            $current->forceFill(['current_document_id' => $document->id, 'revision' => $current->revision + 1])->save();
            DB::afterCommit(fn () => $this->audit->log('legal.document.published', $document, ['version' => $version, 'sha256' => $document->document_sha256], $actor->id));

            return $document;
        }, 5);
    }

    /** Structural completeness is not a claim of jurisdiction-wide legal compliance. */
    public function trader(LegalMarket $market): array
    {
        $data = Validator::make(['trader' => $market->trader], [
            'trader' => ['required', 'array:name,address,email,phone,registration,tax_id'],
            'trader.name' => ['required', 'string', 'max:200'],
            'trader.address' => ['required', 'string', 'max:1000'],
            'trader.email' => ['required', 'string', 'email:rfc', 'max:191'],
            'trader.phone' => ['required', 'string', 'regex:/^[+0-9 ()-]{7,40}$/D'],
            'trader.registration' => ['sometimes', 'nullable', 'string', 'max:100'],
            'trader.tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
        ])->validate()['trader'];
        $trader = [];
        foreach ($data as $key => $value) {
            $trader[$key] = is_string($value) ? trim($value) : '';
        }
        ksort($trader, SORT_STRING);

        return $trader;
    }
}
