@extends('theme::layouts.app')
@section('title', __('Themes'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Make it your community.') }}</h1><p>{{ __('Five distinct layouts. Your content, accounts and orders stay connected.') }}</p></header>@include('theme::admin.nav')@include('theme::partials.errors')<div class="theme-list">
@foreach($themes ?? [] as $availableId => $manifest)
@php($availableId = $manifest['id'] ?? $availableId)
<section class="theme-choice"><h2>{{ $manifest['name'] }}</h2><p>{{ __($manifest['description'] ?? '') }}</p><p class="small muted">{{ __('Navigation') }}: {{ __($manifest['layout']['navigation'] ?? '') }} — {{ __('Catalogue') }}: {{ __($manifest['layout']['catalog'] ?? '') }}</p><form method="post" action="{{ route('admin.themes.update') }}" class="form">@csrf @method('PUT')<input type="hidden" name="theme" value="{{ $availableId }}"><div class="form-actions"><button class="button {{ ($activeTheme ?? $themeId) === $availableId ? 'button-quiet' : '' }}" type="submit" @disabled(($activeTheme ?? $themeId) === $availableId)>{{ ($activeTheme ?? $themeId) === $availableId ? __('Active theme') : __('Use this theme') }}</button><a href="{{ route('admin.themes.tokens', $availableId) }}">{{ __('Theme colours') }}</a><a href="{{ route('admin.appearance', ['theme' => $availableId]) }}">{{ __('Theme settings') }}</a></div></form></section>
@endforeach</div></div>
@endsection
