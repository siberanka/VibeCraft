@extends('theme::layouts.app')
@section('title', __('Legal documents'))
@section('content')
<div class="wrap page legal-portal">
    <header class="page-head"><div class="flow flow-between"><h1>{{ __('Legal documents') }}</h1><a class="button" href="{{ route('admin.legal.markets.create') }}">{{ __('Create market profile') }}</a></div><p>{{ __('Prepare trader details and reviewed document versions for each country and language.') }}</p></header>
    @include('theme::admin.nav')@include('theme::partials.errors')
    <section class="legal-selection-settings"><h2>{{ __('Automatic document selection') }}</h2><p>{{ __('Documents follow the interface language. Choose the site’s default legal profile here; ask visitors for residence only when your country coverage requires it.') }}</p>
        <form method="post" action="{{ route('admin.legal.preferences') }}" class="form">@csrf<input type="hidden" name="revision" value="{{ old('revision', $selectionRevision) }}">
            <div class="field"><label for="legal-selection-mode">{{ __('Document profile selection') }}</label><select id="legal-selection-mode" name="selection_mode"><option value="operator" @selected($selectionMode === 'operator')>{{ __('Automatic from site settings') }}</option><option value="customer" @selected($selectionMode === 'customer')>{{ __('Ask residence for regional coverage') }}</option></select></div>
            <div class="field"><label for="legal-default-market">{{ __('Default document profile') }}</label><select id="legal-default-market" name="default_market_id"><option value="0">{{ __('Use the only enabled profile') }}</option>@foreach($availableDefaults as $defaultMarket)<option value="{{ $defaultMarket->id }}" @selected($defaultMarketId === $defaultMarket->id)>{{ $countries[$defaultMarket->country] ?? $defaultMarket->country }}@if($defaultMarket->subdivision) / {{ $defaultMarket->subdivision }}@endif</option>@endforeach</select><p class="hint">{{ __('A language change never selects another country. If several profiles are enabled, choose a default explicitly.') }}</p></div>
            <button class="button" type="submit">{{ __('Save document selection') }}</button>
        </form>
    </section>
    @if($markets->isEmpty())
        @include('theme::partials.empty', ['emptyTitle' => __('No legal markets yet'), 'emptyText' => __('Start with the countries you intend to serve. New profiles remain paused until reviewed documents are ready.')])
    @else
        <p class="hint legal-table-hint" id="legal-market-table-help">{{ __('Scroll the table sideways on small screens to reach profile actions.') }}</p>
        <div class="table-wrap" role="region" aria-label="{{ __('Legal market profiles') }}" aria-describedby="legal-market-table-help" tabindex="0"><table class="data-table"><thead><tr><th scope="col">{{ __('Country or territory') }}</th><th scope="col">{{ __('Document languages') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead><tbody>
            @foreach($markets as $market)<tr><td>{{ $countries[$market->country] ?? $market->country }}@if($market->subdivision)<span class="hint">{{ $market->subdivision }}</span>@endif</td><td>{{ implode(', ', $market->locales) }}</td><td>{{ $market->is_enabled ? __('Enabled after operator review') : __('Paused') }}</td><td><div class="table-actions"><a href="{{ route('admin.legal.markets.edit', $market) }}">{{ __('Edit trader details') }}</a><a href="{{ route('admin.legal.markets.documents', $market) }}">{{ __('Documents and review') }}</a></div></td></tr>@endforeach
        </tbody></table></div>
        @include('theme::partials.pagination', ['paginator' => $markets])
    @endif
</div>
@endsection
