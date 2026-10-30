@php
    $errorTheme = 'atlas';
    $errorCss = '';
    $errorSite = (string) config('app.name', 'VibeCraft');
    try {
        $errorThemes = app(\App\Core\Theme\ThemeManager::class);
        $errorTheme = $errorThemes->active();
        $errorCss = $errorThemes->cssVariables();
        $errorSite = (string) app(\App\Core\Settings\Settings::class)->get('site.name', $errorSite);
    } catch (\Throwable) {
        // Error recovery does not depend on session, account or navigation queries.
    }
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $errorTheme }}" data-error-code="{{ $statusCode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="{{ $errorTheme === 'mono' ? 'light' : 'dark' }}">
    <title>{{ $heading }} — {{ $errorSite }}</title>
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/assets/themes/{{ $errorTheme }}.css">
    <link rel="stylesheet" href="/assets/minecraft.css">
    <link rel="stylesheet" href="/assets/interface.css">
    <link rel="stylesheet" href="/assets/world-details.css">
    <style nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}">{!! $errorCss !!}</style>
</head>
<body class="portal-page failure-page">
<a class="skip-link" href="#main">{{ __('Skip to content') }}</a>
<div class="error-shell">
    <header class="error-header wrap">
        <a class="wordmark" href="/">{{ $errorSite }}</a>
        <span class="small">{{ __('Minecraft community') }}</span>
    </header>
    <main class="error-main wrap" id="main" tabindex="-1">
        <div class="error-waystone" aria-hidden="true">
            @include('errors::gateway')
            <span class="error-code">{{ $statusCode }}</span>
        </div>
        <div class="error-copy">
            <h1>{{ $heading }}</h1>
            <p>{{ $description }}</p>
            <div class="error-actions">
                @if($statusCode === 419)
                    <a class="button" href="/login">{{ __('Sign in again') }}</a>
                    <a class="button button-quiet" href="/">{{ __('Return to the community') }}</a>
                @else
                    <a class="button" href="/">{{ $statusCode === 503 ? __('Check again') : __('Return to the community') }}</a>
                    @if(in_array($statusCode, [403, 404, 500], true))
                        <a class="button button-quiet" href="/tickets">{{ __('Support') }}</a>
                    @endif
                @endif
            </div>
        </div>
    </main>
    <footer class="error-footer wrap"><span>{{ $errorSite }}</span><a href="/servers">{{ __('Servers') }}</a></footer>
</div>
</body>
</html>
