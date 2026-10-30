<?php

namespace App\Http\Controllers;

use App\Domain\Legal\CountryCatalog;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalPublisher;
use App\Domain\Legal\LegalScope;
use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use App\View\LegalView;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function __construct(private LegalConsumer $consumer, private LegalScope $scope) {}

    public function index(Request $request): View
    {
        $chooses = $this->scope->customerChooses();
        $data = $request->validate([
            'country' => ['nullable', 'string', Rule::in(array_keys(CountryCatalog::options('en')))],
            'subdivision' => ['nullable', 'string', 'max:8', 'regex:/^[A-Z0-9-]*$/D'],
            'kind' => ['nullable', Rule::in(LegalPublisher::KINDS)],
        ]);
        $country = $data['country'] ?? '';
        $subdivision = $data['subdivision'] ?? '';
        if (! $chooses) {
            $default = $this->scope->defaultMarket();
            $country = $default ? $default->country : '';
            $subdivision = $default ? $default->subdivision : '';
        }
        $profiles = $country !== '' ? LegalMarket::where('country', $country)->where('is_enabled', true)->whereNotNull('reviewed_at')->orderBy('subdivision')->limit(256)->get() : collect();
        $market = $profiles->firstWhere('subdivision', $subdivision);
        $documents = collect();
        if ($market && in_array(app()->getLocale(), $market->locales, true)) {
            try {
                $documents = collect($this->consumer->documents($market, app()->getLocale(), 'checkout'));
                if (isset($data['kind'])) {
                    $documents = $documents->only($data['kind']);
                }
                $documents = $documents->values();
            } catch (ValidationException) {
                $documents = collect();
            }
        }

        return view('theme::legal.index', ['countries' => CountryCatalog::options(), 'country' => $country, 'subdivision' => $subdivision, 'profiles' => $profiles, 'market' => $market, 'documents' => $documents, 'kinds' => LegalView::labels(), 'selectedKind' => $data['kind'] ?? null, 'requiresMarketChoice' => $chooses]);
    }

    public function show(Request $request, LegalDocument $document): View|Response
    {
        $current = LegalPolicy::whereKey($document->legal_policy_id)->where('current_document_id', $document->id)->exists();
        $owned = $request->user() && LegalAcceptance::where('legal_document_id', $document->id)->where('user_id', $request->user()->id)->exists();
        abort_unless($current || $owned, 404);
        $view = LegalView::document($document);
        $data = $request->validate(['download' => ['nullable', 'boolean']]);
        if (! empty($data['download'])) {
            $lines = [$document->title, __('Published version :number', ['number' => $document->version]), $document->country.' '.$document->subdivision, $document->locale, $document->published_at->toIso8601String(), ''];
            foreach ($document->trader_snapshot as $value) {
                if ($value !== '') {
                    $lines[] = $value;
                }
            }
            $lines[] = "\n".$document->body_markdown;
            $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $document->kind.'-'.$document->country.'-'.$document->locale.'-v'.$document->version).'.txt';

            return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'Cache-Control' => 'no-store, private, max-age=0']);
        }

        return view('theme::legal.document', $view + ['isCurrent' => $current]);
    }

    public function cookies(): View
    {
        return view('theme::legal.cookies', ['sessionCookie' => (string) config('session.cookie'), 'rememberCookie' => auth()->guard('web')->getRecallerName()]);
    }
}
