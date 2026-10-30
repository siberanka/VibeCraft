@extends('theme::layouts.app')
@section('title', __('Documents and review'))
@section('content')
<div class="wrap page legal-portal">
    <header class="page-head"><h1>{{ __('Documents and review') }}</h1><p>{{ $countryName }}@if($market->subdivision) / {{ $market->subdivision }}@endif</p></header>
    @include('theme::admin.nav')@include('theme::partials.errors')
    <div class="form-actions"><a class="button button-quiet" href="{{ route('admin.legal.markets.edit', $market) }}">{{ __('Edit trader details') }}</a><a class="button button-quiet" href="{{ route('admin.legal.index') }}">{{ __('All market profiles') }}</a></div>
    <p class="legal-status">{{ $market->is_enabled ? __('Enabled after operator review') : __('Paused') }}@if($market->reviewed_at) <time datetime="{{ $market->reviewed_at->toIso8601String() }}">{{ $market->reviewed_at->translatedFormat('d F Y H:i') }}</time>@endif</p>
    @foreach($market->locales as $documentLocale)
        <section class="legal-language"><h2>{{ $documentLocale === 'tr' ? 'Türkçe' : 'English' }}</h2><p class="hint">{{ ($ready[$documentLocale] ?? false) ? __('Published documents match this trader profile.') : __('Complete and publish each document using the current trader details.') }}</p>
            <ol class="legal-document-list">
                @foreach($kinds as $kind => $kindLabel)
                    @php($policy = $policies->get($documentLocale.':'.$kind))
                    <li><div><h3>{{ $kindLabel }}</h3>@if($policy)<p lang="{{ $policy->locale }}">{{ $policy->title }}</p><p class="hint">{{ __('Draft revision :number', ['number' => $policy->revision]) }}@if($policy->currentDocument) / {{ __('Published version :number', ['number' => $policy->currentDocument->version]) }}@else / {{ __('Not published') }}@endif</p>@else<p class="hint">{{ __('No draft yet') }}</p>@endif</div>
                        <div class="form-actions">
                            <a class="button button-quiet" href="{{ $policy ? route('admin.legal.policies.edit', [$market, $policy]) : route('admin.legal.policies.create', [$market, 'kind' => $kind, 'locale' => $documentLocale]) }}">{{ $policy ? __('Edit draft') : __('Prepare draft') }}</a>
                            @if($policy)<a class="button button-quiet" href="{{ route('admin.legal.policies.preview', [$market, $policy]) }}">{{ __('Review and publish') }}</a>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endforeach
    <section class="legal-activation"><h2>{{ __('Operator review') }}</h2><p>{{ __('Check the country requirements, trader identity, languages, and all published versions before enabling new agreements.') }}</p>
        <form method="post" action="{{ route('admin.legal.markets.enable', $market) }}" class="form">@csrf<input type="hidden" name="revision" value="{{ old('revision', $market->revision) }}">
            <label class="checkbox"><input type="checkbox" name="review_confirmation" value="1" required><span>{{ __('I reviewed the documents and trader details for this market and its selected languages.') }}</span></label>
            <button class="button" type="submit" @disabled(in_array(false, $ready, true) || $ready === [])>{{ __('Enable this market') }}</button>
        </form>
    </section>
</div>
@endsection
