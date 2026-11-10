@extends('theme::layouts.app')
@section('title', __('Themes'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Make it your community.') }}</h1><p>{{ __('Pick a look for your site. Your content, accounts and orders stay connected.') }}</p></header>@include('theme::admin.nav')@include('theme::partials.errors')<div class="theme-cards">
@foreach($themes ?? [] as $availableId => $manifest)
@php($availableId = $manifest['id'] ?? $availableId)
@php($isActive = ($activeTheme ?? $themeId) === $availableId)
<section class="theme-card {{ $isActive ? 'is-active' : '' }}" aria-labelledby="theme-{{ $availableId }}">
    <img class="theme-card-preview" src="{{ asset('assets/images/'.$availableId.'-landscape-768.webp') }}" alt="" width="768" height="432" loading="lazy">
    <div class="theme-card-body">
        <div class="theme-card-head">
            <h2 id="theme-{{ $availableId }}">{{ $manifest['name'] }}</h2>
            @if($isActive)<span class="order-status">{{ __('Active theme') }}</span>@endif
        </div>
        <p>{{ __($manifest['description'] ?? '') }} — {{ ($manifest['light'] ?? false) ? __('Light') : __('Dark') }}</p>
        <svg class="theme-palette" viewBox="0 0 120 20" width="120" height="20" role="img" aria-label="{{ __('Theme colours') }}">
            @foreach(['c-bg', 'c-surface', 'c-line', 'c-muted', 'c-ink', 'c-accent'] as $swatchIndex => $swatchToken)
            <rect x="{{ $swatchIndex * 20 }}" y="0" width="20" height="20" fill="{{ $manifest['effective'][$swatchToken] ?? '#000000' }}"/>
            @endforeach
        </svg>
        <form method="post" action="{{ route('admin.themes.update') }}" class="form-actions">@csrf @method('PUT')<input type="hidden" name="theme" value="{{ $availableId }}">
            <button class="button button-small {{ $isActive ? 'button-quiet' : '' }}" type="submit" @disabled($isActive)>{{ $isActive ? __('Active theme') : __('Use this theme') }}</button>
            <a href="{{ route('admin.themes.tokens', $availableId) }}">{{ __('Colours') }}</a>
            <a href="{{ route('admin.appearance', ['theme' => $availableId]) }}">{{ __('Layout and artwork') }}</a>
        </form>
    </div>
</section>
@endforeach</div></div>
@endsection
