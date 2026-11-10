<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeId ?? 'atlas' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="{{ $themeScheme ?? 'dark' }}">
    {{-- Laravel escapes inline sections and fallback values; escape markup without encoding their entities twice. --}}
    <meta name="description" content="{!! e($__env->yieldContent('description', $site['description'] ?? ''), false) !!}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{!! e(trim($__env->yieldContent('title', $site['name'] ?? 'VibeCraft')), false) !!}@if($__env->hasSection('title')) — {{ $site['name'] ?? 'VibeCraft' }}@endif</title>
    <link rel="stylesheet" href="{{ asset('assets/site.css') }}">
    @unless($themeLate ?? false)<link rel="stylesheet" href="{{ asset('assets/themes/'.($themeId ?? 'atlas').'.css') }}">@endunless
    <link rel="stylesheet" href="{{ asset('assets/minecraft.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/interface.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/world-details.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/knowledge.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/showcase.css') }}">
    @if($themeLate ?? false)<link rel="stylesheet" href="{{ asset('assets/themes/'.($themeId ?? 'atlas').'.css') }}">@endif
    @if(!empty($site['custom_css']) && ! request()->is('admin', 'admin/*'))<link rel="stylesheet" href="{{ route('custom-css', ['v' => $site['custom_css']]) }}">@endif
    <style nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}">{!! $themeCss ?? '' !!}</style>
    <noscript><style nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}">[data-copy]{display:none!important}</style></noscript>
    <script type="module" nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}" src="{{ asset('assets/site.js') }}"></script>
    @stack('head')
</head>
<body class="{{ request()->routeIs('home') ? 'home-page' : 'portal-page' }}">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
@if(!empty($site['announcement']))
    <div class="announcement">{{ $site['announcement'] }}</div>
@endif
<header class="site-header">
    <div class="wrap">
        <div class="masthead">
            <a href="{{ route('home') }}" class="wordmark" aria-label="{{ $site['name'] }} — {{ __('Home') }}">
                @if(!empty($site['logo']))<img src="{{ $site['logo'] }}" alt="{{ $site['name'] }}" width="180" height="54">@else @include('theme::partials.brand-voxel')<span class="wordmark-content"><span>{{ $site['name'] }}</span><small>{{ $site['tagline'] ?: __('Minecraft community') }}</small></span>@endif
            </a>
            <div class="masthead-tools">
                @include('theme::partials.copy-button', ['address' => $site['java_address'] ?? '', 'editionLabel' => 'Java'])
                @include('theme::partials.online')
                @if(!empty($site['discord']))<a class="community-link" href="{{ $site['discord'] }}" rel="noopener noreferrer">Discord</a>@endif
            </div>
        </div>
        <div class="navigation-row">
            <nav class="site-navigation" aria-label="{{ __('Main navigation') }}">
                @include('theme::partials.nav-links')
                @unless(in_array('community', $site['nav_hidden'] ?? [], true))@include('theme::partials.community-menu')@endunless
                <div class="nav-account">
                    @include('theme::partials.session-menu')
                </div>
            </nav>
        </div>
        <details class="mobile-navigation" data-disclosure>
            <summary>@include('theme::partials.icon', ['icon' => 'menu']) {{ __('Menu') }} @include('theme::partials.icon', ['icon' => 'chevron', 'iconClass' => 'menu-chevron'])</summary>
            <nav aria-label="{{ __('Mobile navigation') }}">
                @include('theme::partials.nav-links')
                @unless(in_array('community', $site['nav_hidden'] ?? [], true))@include('theme::partials.community-menu')@endunless
                @include('theme::partials.session-menu')
            </nav>
        </details>
        <div class="mobile-connect">
            @include('theme::partials.copy-button', ['address' => $site['java_address'] ?? '', 'editionLabel' => 'Java'])
            @include('theme::partials.online')
        </div>
    </div>
</header>
<main id="main" tabindex="-1">
    <div class="wrap"><p class="copy-status sr-only" data-copy-status role="status" aria-live="polite"></p></div>
    @include('theme::partials.context-path')
    @if(session('status'))<div class="wrap"><div class="alert alert-success" role="status">{{ __(session('status')) }}</div></div>@endif
    @if(session('success'))<div class="wrap"><div class="alert alert-success" role="status">{{ __(session('success')) }}</div></div>@endif
    @if(session('warning'))<div class="wrap"><div class="alert" role="alert">{{ __(session('warning')) }}</div></div>@endif
    @if(session('bridge_secret'))
        <section class="wrap" aria-label="{{ __('Game bridge credentials') }}">
            <div class="alert">
                <p><strong>{{ __('Copy these server credentials now. They are shown only once.') }}</strong></p>
                <div class="form form-footer">
                    <div class="field"><label for="new-bridge-key">{{ __('Key ID') }}</label><input id="new-bridge-key" value="{{ session('bridge_key_id') }}" readonly autocomplete="off"></div>
                    <div class="field"><label for="new-bridge-secret">{{ __('Secret') }}</label><input id="new-bridge-secret" value="{{ session('bridge_secret') }}" readonly autocomplete="off" spellcheck="false"></div>
                </div>
            </div>
        </section>
    @endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="wrap">
        <div class="footer-layout">
            <div class="footer-brand">
                <a class="wordmark" href="{{ route('home') }}">{{ $site['name'] }}</a>
                @if(!empty($site['tagline']))<p>{{ $site['tagline'] }}</p>@endif
                <div class="footer-join">@include('theme::partials.copy-button', ['address' => $site['java_address'] ?? '', 'editionLabel' => 'Java'])</div>
                <p>{{ __('Not an official Minecraft product. Not approved by or associated with Mojang or Microsoft.') }}</p>
            </div>
            <nav class="footer-links" aria-label="{{ __('Footer navigation') }}">
                <a href="{{ route('legal.index') }}">{{ __('Legal information') }}</a>
                <a href="{{ route('legal.index', ['kind' => 'privacy']) }}">{{ __('Privacy information') }}</a>
                <a href="{{ route('legal.cookies') }}">{{ __('Cookies and preferences') }}</a>
                @foreach($navPages ?? [] as $navPage)<a href="{{ route('pages.show', $navPage) }}">{{ $navPage->title }}</a>@endforeach
                @foreach($site['menus']['footer'] ?? [] as $menuItem)@include('theme::partials.menu-link', ['menuItem' => $menuItem])@endforeach
                @if(!empty($site['discord']))<a href="{{ $site['discord'] }}" rel="noopener noreferrer">Discord</a>@endif
                @foreach($site['social'] ?? [] as $label => $socialUrl)<a href="{{ $socialUrl }}" rel="noopener noreferrer">{{ $label }}</a>@endforeach
                <form action="{{ url('/locale') }}" method="post" class="flow">
                    @csrf
                    <label class="sr-only" for="site-locale">{{ __('Language') }}</label>
                    <select id="site-locale" name="locale" aria-label="{{ __('Language') }}">
                        <option value="tr" @selected(app()->getLocale() === 'tr')>Türkçe</option>
                        <option value="en" @selected(app()->getLocale() === 'en')>English</option>
                    </select>
                    <button type="submit" class="text-button">{{ __('Apply') }}</button>
                </form>
            </nav>
        </div>
        @if(!empty($site['footer_note']))<p class="footer-note">{{ $site['footer_note'] }}</p>@endif
    </div>
</footer>
@include('theme::partials.confirmation')
@stack('scripts')
</body>
</html>
