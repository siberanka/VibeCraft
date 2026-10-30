@extends('theme::layouts.app')
@section('title', $document->title)
@section('content')
<div class="wrap page legal-portal">
    <nav class="breadcrumbs" aria-label="{{ __('Breadcrumb') }}"><a href="{{ route('legal.index', ['country' => $document->country, 'subdivision' => $document->subdivision]) }}">{{ __('Legal information') }}</a><span>{{ $countryName }}</span></nav>
    <header class="page-head"><h1 lang="{{ $document->locale }}">{{ $document->title }}</h1><p>{{ __('Published version :number', ['number' => $document->version]) }} / <time datetime="{{ $document->published_at->toIso8601String() }}">{{ $document->published_at->translatedFormat('d F Y') }}</time></p>@if(!$isCurrent)<p class="hint">{{ __('This is a previously recorded version.') }}</p>@endif</header>
    <div class="legal-document-layout">
        @include('theme::legal.trader', ['traderSnapshot' => $document->trader_snapshot, 'traderSubdivision' => $document->subdivision, 'documentLocale' => $document->locale])
        <article class="legal-copy">
            @if($rendered['toc'])<details class="knowledge-toc legal-toc" open><summary>{{ __('On this page') }}</summary><nav aria-label="{{ __('On this page') }}"><ol>@foreach($rendered['toc'] as $heading)<li class="knowledge-depth-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a></li>@endforeach</ol></nav></details>@endif
            <div class="prose knowledge-prose" lang="{{ $document->locale }}">{!! $rendered['html'] !!}</div>
            <div class="form-actions legal-copy-actions"><a class="button button-quiet" href="{{ route('legal.documents.show', [$document, 'download' => 1]) }}">{{ __('Download a text copy') }}</a><a class="button button-quiet" href="{{ route('legal.index', ['country' => $document->country, 'subdivision' => $document->subdivision]) }}">{{ __('All documents for this market') }}</a></div>
        </article>
    </div>
</div>
@endsection
