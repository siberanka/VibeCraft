@extends('theme::layouts.app')
@section('title', __('Review legal draft'))
@section('content')
<div class="wrap page legal-portal">
    <header class="page-head"><h1>{{ __('Review legal draft') }}</h1><p lang="{{ $policy->locale }}">{{ $policy->title }}</p></header>
    @include('theme::admin.nav')@include('theme::partials.errors')
    <div class="form-actions"><a class="button button-quiet" href="{{ route('admin.legal.policies.edit', [$market, $policy]) }}">{{ __('Edit draft') }}</a><a class="button button-quiet" href="{{ route('admin.legal.markets.documents', $market) }}">{{ __('Documents and review') }}</a></div>
    <p class="hint">{{ __('Draft revision :number', ['number' => $policy->revision]) }}</p>
    <div class="legal-document-layout">
        @include('theme::legal.trader', ['traderSnapshot' => $market->trader, 'traderSubdivision' => $market->subdivision, 'documentLocale' => $policy->locale, 'countryName' => \App\Domain\Legal\CountryCatalog::options()[$market->country] ?? $market->country])
        <section class="legal-copy" lang="{{ $policy->locale }}"><h2>{{ $policy->title }}</h2><div class="prose knowledge-prose">{!! $rendered['html'] !!}</div></section>
    </div>
    <section class="legal-activation"><h2>{{ __('Publish reviewed version') }}</h2><p>{{ __('The published version preserves this text and the current trader details. Complete all placeholders before publishing.') }}</p>
        <form method="post" action="{{ route('admin.legal.policies.publish', [$market, $policy]) }}" class="form">@csrf<input type="hidden" name="revision" value="{{ old('revision', $policy->revision) }}">
            <label class="checkbox"><input type="checkbox" name="publication_confirmation" value="1" required><span>{{ __('I reviewed this text and trader information and want to publish this version.') }}</span></label>
            <button class="button" type="submit">{{ __('Publish reviewed version') }}</button>
        </form>
    </section>
</div>
@endsection
