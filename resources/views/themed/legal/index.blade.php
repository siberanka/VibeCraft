@extends('theme::layouts.app')
@section('title', __('Legal information'))
@section('content')
<div class="wrap page legal-portal">
    <header class="page-head legal-head">@include('theme::partials.voxel-emblem', ['emblem' => 'journal'])<div><h1>{{ __('Legal information') }}</h1><p>{{ $requiresMarketChoice ? __('Find the published terms and notices for your country and language.') : __('Read the published terms and notices in your selected language.') }}</p></div></header>
    @include('theme::partials.errors')
    @if($requiresMarketChoice)
    <form method="get" action="{{ route('legal.index') }}" class="legal-country-form form">
        @if($selectedKind)<input type="hidden" name="kind" value="{{ $selectedKind }}">@endif
        <div class="field"><label for="legal-country">{{ __('Country or territory') }}</label><select id="legal-country" name="country" required><option value="">{{ __('Choose a country') }}</option>@foreach($countries as $code => $name)<option value="{{ $code }}" @selected($country === $code)>{{ $name }}</option>@endforeach</select></div>
        @if($profiles->where('subdivision', '!=', '')->isNotEmpty())<div class="field"><label for="legal-subdivision">{{ __('Subdivision') }}</label><select id="legal-subdivision" name="subdivision"><option value="">{{ $profiles->contains('subdivision', '') ? __('Country-wide profile') : __('Choose a subdivision') }}</option>@foreach($profiles->where('subdivision', '!=', '') as $profile)<option value="{{ $profile->subdivision }}" @selected($subdivision === $profile->subdivision)>{{ $profile->subdivision }}</option>@endforeach</select></div>@endif
        <button class="button" type="submit">{{ __('Show documents') }}</button>
    </form>
    @endif
    @if($documents->isNotEmpty())
        <section class="legal-publications"><h2>{{ $countries[$country] ?? $country }}</h2><ol class="legal-document-list">
            @foreach($documents as $document)<li><div><h3><a href="{{ route('legal.documents.show', $document) }}" lang="{{ $document->locale }}">{{ $document->title }}</a></h3><p class="hint">{{ $kinds[$document->kind] ?? $document->kind }} / {{ __('Published version :number', ['number' => $document->version]) }}</p><time datetime="{{ $document->published_at->toIso8601String() }}">{{ $document->published_at->translatedFormat('d F Y') }}</time></div><a class="button button-quiet" href="{{ route('legal.documents.show', $document) }}">{{ __('Read document') }}</a></li>@endforeach
        </ol></section>
    @elseif($country !== '')
        @include('theme::partials.empty', ['emptyTitle' => __('No published documents for this selection'), 'emptyText' => __('This country, subdivision or language is not ready for new agreements. Choose your actual country; an unsupported market cannot use another country profile.')])
    @else
        <p class="legal-intro">{{ $requiresMarketChoice ? __('Country, document language and payment currency are separate choices. Choose your country to see available published documents.') : __('Published documents are not available for this language yet. Please contact the community team.') }}</p>
    @endif
    <p><a href="{{ route('legal.cookies') }}">{{ __('Cookies and preferences') }}</a></p>
</div>
@endsection
