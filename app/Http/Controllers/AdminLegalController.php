<?php

namespace App\Http\Controllers;

use App\Core\Settings\Settings;
use App\Domain\Content\WikiRenderer;
use App\Domain\Legal\CountryCatalog;
use App\Domain\Legal\LegalConsumer;
use App\Domain\Legal\LegalMarkets;
use App\Domain\Legal\LegalPublisher;
use App\Domain\Legal\LegalScope;
use App\Models\LegalMarket;
use App\Models\LegalPolicy;
use App\View\LegalView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLegalController extends Controller
{
    public function __construct(private LegalMarkets $markets, private LegalPublisher $publisher, private LegalConsumer $consumer) {}

    public function index(): View
    {
        $settings = app(Settings::class);

        return view('theme::admin.legal.index', ['markets' => LegalMarket::orderBy('country')->orderBy('subdivision')->paginate(20), 'countries' => CountryCatalog::options(),
            'availableDefaults' => LegalMarket::where('is_enabled', true)->whereNotNull('reviewed_at')->orderBy('country')->orderBy('subdivision')->limit(512)->get(),
            'selectionMode' => $settings->get('legal.market_selection'), 'defaultMarketId' => (int) $settings->get('legal.default_market_id'), 'selectionRevision' => (int) $settings->get('legal.selection_revision')]);
    }

    public function preferences(Request $request, LegalScope $scope): RedirectResponse
    {
        $scope->configure($request->user(), $request->all());

        return redirect()->route('admin.legal.index')->with('status', __('Document selection settings saved.'));
    }

    public function marketForm(?LegalMarket $market = null): View
    {
        $trader = $market ? $market->trader : [];
        $fields = [
            'country' => ['label' => __('Country or territory'), 'type' => 'select', 'required' => true, 'value' => $market?->country, 'options' => ['' => __('Choose a country')] + CountryCatalog::options()],
            'subdivision' => ['label' => __('Subdivision code (optional)'), 'value' => $market?->subdivision, 'max' => 8, 'hint' => __('Use a separate profile where a state or region needs different terms.')],
            'locales' => ['label' => __('Document languages'), 'type' => 'checkboxes', 'value' => $market ? $market->locales : [], 'options' => ['tr' => 'Türkçe', 'en' => 'English'], 'hint' => __('Every selected language needs all six document types before this market can be enabled.')],
        ];
        foreach (['name' => ['Trader name', 'text', 200], 'address' => ['Trader address', 'textarea', 1000], 'email' => ['Trader email', 'email', 191], 'phone' => ['Trader phone', 'tel', 40], 'registration' => ['Business registration (if applicable)', 'text', 100], 'tax_id' => ['Tax registration (if applicable)', 'text', 100]] as $key => [$label, $type, $max]) {
            $fields['trader_'.$key] = ['label' => __($label), 'type' => $type, 'value' => $trader[$key] ?? '', 'max' => $max, 'required' => false];
        }

        return view('theme::admin.form', [
            'title' => __('Legal market profile'), 'resource' => 'legal', 'record' => $market, 'fields' => $fields,
            'contentRevision' => $market?->revision, 'action' => $market ? route('admin.legal.markets.update', $market) : route('admin.legal.markets.store'), 'method' => $market ? 'PUT' : 'POST',
            'saveNotice' => __('These trader details appear in published documents. Saving pauses this market until its documents and operator review are current.'),
            'extraActions' => $market ? [['url' => route('admin.legal.markets.documents', $market), 'label' => __('Documents and review')]] : [],
        ]);
    }

    public function saveMarket(Request $request, ?LegalMarket $market = null): RedirectResponse
    {
        $record = $this->markets->save($market, $request->user(), $request->all());

        return redirect()->route('admin.legal.markets.documents', $record)->with('status', __('Market draft saved. Review the documents before enabling it.'));
    }

    public function documents(LegalMarket $market): View
    {
        $policies = $market->policies()->whereIn('kind', LegalPublisher::KINDS)->whereIn('locale', $market->locales)->with('currentDocument')->get()->keyBy(fn (LegalPolicy $policy) => $policy->locale.':'.$policy->kind);
        $ready = [];
        foreach ($market->locales as $locale) {
            try {
                $this->consumer->documents($market, $locale, 'checkout');
                $ready[$locale] = true;
            } catch (ValidationException) {
                $ready[$locale] = false;
            }
        }

        return view('theme::admin.legal.documents', ['market' => $market, 'policies' => $policies, 'ready' => $ready, 'kinds' => LegalView::labels(), 'countryName' => CountryCatalog::options()[$market->country] ?? $market->country]);
    }

    public function policyForm(Request $request, LegalMarket $market, ?LegalPolicy $policy = null): View
    {
        abort_if($policy && $policy->legal_market_id !== $market->id, 404);
        $query = $request->validate(['kind' => ['nullable', Rule::in(LegalPublisher::KINDS)], 'locale' => ['nullable', Rule::in($market->locales)]]);
        $fields = [
            'kind' => ['label' => __('Document type'), 'type' => 'select', 'required' => true, 'value' => $policy ? $policy->kind : ($query['kind'] ?? null), 'options' => ['' => __('Choose a document type')] + LegalView::labels(), 'disabled' => $policy !== null],
            'locale' => ['label' => __('Document language'), 'type' => 'select', 'required' => true, 'value' => $policy ? $policy->locale : ($query['locale'] ?? null), 'options' => ['' => __('Choose a language')] + array_intersect_key(['tr' => 'Türkçe', 'en' => 'English'], array_flip($market->locales)), 'disabled' => $policy !== null],
            'title' => ['label' => __('Title'), 'value' => $policy?->title, 'required' => true, 'max' => 160],
            'body_markdown' => ['label' => __('Document text'), 'type' => 'textarea', 'value' => $policy?->body_markdown, 'required' => true, 'max' => 100000, 'rows' => 24],
        ];
        $links = [['url' => route('admin.legal.markets.documents', $market), 'label' => __('Documents and review')]];
        if ($policy) {
            $links[] = ['url' => route('admin.legal.policies.preview', [$market, $policy]), 'label' => __('Preview draft')];
        }

        return view('theme::admin.form', [
            'title' => __('Legal document draft'), 'resource' => 'legal', 'record' => $policy, 'fields' => $fields,
            'contentRevision' => $policy?->revision, 'action' => $policy ? route('admin.legal.policies.update', [$market, $policy]) : route('admin.legal.policies.store', $market), 'method' => $policy ? 'PUT' : 'POST',
            'saveNotice' => __('Save a draft, review its text and trader details, then publish the reviewed version separately.'), 'extraActions' => $links,
        ]);
    }

    public function savePolicy(Request $request, LegalMarket $market, ?LegalPolicy $policy = null): RedirectResponse
    {
        abort_if($policy && $policy->legal_market_id !== $market->id, 404);
        $input = $request->all();
        if ($policy) {
            $input['kind'] = $policy->kind;
            $input['locale'] = $policy->locale;
        }
        $this->publisher->saveDraft($market, $request->user(), $input);

        return redirect()->route('admin.legal.markets.documents', $market)->with('status', __('Draft saved.'));
    }

    public function preview(LegalMarket $market, LegalPolicy $policy): View
    {
        abort_unless($policy->legal_market_id === $market->id, 404);

        return view('theme::admin.legal.preview', ['market' => $market, 'policy' => $policy, 'rendered' => WikiRenderer::render($policy->body_markdown, $policy->locale)]);
    }

    public function publish(Request $request, LegalMarket $market, LegalPolicy $policy): RedirectResponse
    {
        abort_unless($policy->legal_market_id === $market->id, 404);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1', 'max:2147483647'], 'publication_confirmation' => ['accepted']]);
        $this->publisher->publish($policy, (int) $data['revision'], $request->user());

        return redirect()->route('admin.legal.markets.documents', $market)->with('status', __('Document version published.'));
    }

    public function enable(Request $request, LegalMarket $market): RedirectResponse
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1', 'max:2147483647'], 'review_confirmation' => ['accepted']]);
        $this->markets->enable($market, $request->user(), (int) $data['revision'], true);

        return redirect()->route('admin.legal.markets.documents', $market)->with('status', __('Market enabled after operator review.'));
    }
}
