@extends('theme::layouts.app')
@section('title', __('Theme colours'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $name }} — {{ __('Theme colours') }}</h1><p>{{ __('Pick a ready palette or mix your own. Text colours are checked so the site stays readable.') }}</p></header>@include('theme::admin.nav')@include('theme::partials.errors')
<section class="section" aria-labelledby="palettes-heading">
    <h2 id="palettes-heading">{{ __('Ready palettes') }}</h2>
    <div class="palette-grid">
        <form method="post" action="{{ route('admin.themes.tokens.reset', $theme) }}" data-submit-once>@csrf
            <button class="palette-option" type="submit" @if(($activePalette ?? '') === 'default') aria-pressed="true" @endif>
                <svg class="theme-palette" viewBox="0 0 100 20" width="100" height="20" aria-hidden="true" focusable="false">@foreach(['c-bg', 'c-surface', 'c-ink', 'c-accent', 'c-focus'] as $swatchIndex => $swatchToken)<rect x="{{ $swatchIndex * 20 }}" y="0" width="20" height="20" fill="{{ $defaults[$swatchToken] ?? '#000000' }}"/>@endforeach</svg>
                <span>{{ __('Theme default') }}</span>
            </button>
        </form>
        @foreach($palettes ?? [] as $paletteKey => $palette)
        <form method="post" action="{{ route('admin.themes.palette', $theme) }}" data-submit-once>@csrf<input type="hidden" name="palette" value="{{ $paletteKey }}">
            <button class="palette-option" type="submit" @if(($activePalette ?? '') === $paletteKey) aria-pressed="true" @endif>
                <svg class="theme-palette" viewBox="0 0 100 20" width="100" height="20" aria-hidden="true" focusable="false">@foreach(['c-bg', 'c-surface', 'c-ink', 'c-accent', 'c-focus'] as $swatchIndex => $swatchToken)<rect x="{{ $swatchIndex * 20 }}" y="0" width="20" height="20" fill="{{ $palette['tokens'][$swatchToken] ?? ($defaults[$swatchToken] ?? '#000000') }}"/>@endforeach</svg>
                <span>{{ __($palette['name']) }}</span>
            </button>
        </form>
        @endforeach
    </div>
    @if(($activePalette ?? '') === 'custom')<p class="hint">{{ __('You are using your own colours.') }}</p>@endif
</section>
<section class="section" aria-labelledby="custom-heading">
<h2 id="custom-heading">{{ __('Your own colours') }}</h2>
<form method="post" action="{{ route('admin.themes.tokens.update', $theme) }}" class="form token-form" data-submit-once>@csrf @method('PUT')
@foreach($tokens as $tokenName => $tokenValue)
    <div class="field"><label for="token-{{ $tokenName }}">{{ __('token.'.$tokenName) }}</label>
    @if(str_starts_with($tokenName, 'c-'))<input id="token-{{ $tokenName }}" name="tokens[{{ $tokenName }}]" type="color" value="{{ old('tokens.'.$tokenName, $tokenValue) }}">
    @else<input id="token-{{ $tokenName }}" name="tokens[{{ $tokenName }}]" type="number" min="0" max="32" value="{{ (int) old('tokens.'.$tokenName, $tokenValue) }}">@endif
    @error('tokens.'.$tokenName)<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <p class="hint">{{ __('Default') }}: {{ $defaults[$tokenName] ?? '' }}</p></div>
@endforeach
<div class="form-actions"><button class="button" type="submit">{{ __('Save colours') }}</button></div>
</form>
<form method="post" action="{{ route('admin.themes.tokens.reset', $theme) }}" class="form-actions" data-submit-once>@csrf<button class="button button-quiet" type="submit">{{ __('Restore theme defaults') }}</button></form>
</section>
</div>
@endsection
