@extends('theme::layouts.app')
@section('title', __('Theme settings'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Theme settings') }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
<nav class="form-actions" aria-label="{{ __('Theme') }}">@foreach($themeNames as $themeKey => $themeName)<a class="button button-small {{ $themeKey === $theme ? '' : 'button-quiet' }}" href="{{ route('admin.appearance', ['theme' => $themeKey]) }}" @if($themeKey === $theme) aria-current="page" @endif>{{ $themeName }}</a>@endforeach</nav>
<form method="post" action="{{ route('admin.appearance.update') }}" class="form section" data-submit-once>@csrf @method('PUT')
<input type="hidden" name="theme" value="{{ $theme }}">
<fieldset><legend>{{ __('Home sections') }}</legend>
@foreach($rows as $index => $row)
<div class="form-inline order-row">
    <strong class="order-name">{{ __('home.'.$row['key']) }}</strong>
    <div class="field field-narrow"><label for="position-{{ $row['key'] }}">{{ __('Position') }}</label><input id="position-{{ $row['key'] }}" name="sections[{{ $row['key'] }}][position]" type="number" min="1" max="20" value="{{ $index + 1 }}" required></div>
    <label class="checkbox" for="visible-{{ $row['key'] }}"><input id="visible-{{ $row['key'] }}" name="sections[{{ $row['key'] }}][visible]" type="checkbox" value="1" @checked($row['visible'])><span>{{ __('Show') }}</span></label>
</div>
@endforeach
</fieldset>
<fieldset class="art-choice"><legend>{{ __('Home artwork') }}</legend>
<p class="hint">{{ __('Each theme comes with its own picture. Keep it, use one of your images, or show the home page without a picture.') }}</p>
<div class="art-options">
    <label class="art-option" for="art-default"><input id="art-default" type="radio" name="art_mode" value="default" @checked(old('art_mode', $art['mode']) === 'default')>
        <img src="{{ asset('assets/images/'.$theme.'-landscape-768.webp') }}" alt="" width="192" height="108" loading="lazy"><span>{{ __('Theme picture') }}</span></label>
    <label class="art-option" for="art-own"><input id="art-own" type="radio" name="art_mode" value="own" @checked(old('art_mode', $art['mode']) === 'own')><span>{{ __('My own image') }}</span></label>
    <label class="art-option" for="art-none"><input id="art-none" type="radio" name="art_mode" value="none" @checked(old('art_mode', $art['mode']) === 'none')><span>{{ __('No picture') }}</span></label>
</div>
@include('theme::partials.media-picker', ['pickerName' => 'art_media_id', 'pickerId' => 'art-media', 'pickerLabel' => __('My own image'), 'pickerValue' => old('art_media_id', $art['media']), 'pickerHint' => __('Used when "My own image" is selected. A wide picture of at least 1600 pixels works best.')])
</fieldset>
<fieldset class="pointer-choice"><legend>{{ __('Pointer effects') }}</legend>
<p class="hint">{{ __('A small live effect follows the mouse and answers clicks; on phones and tablets it answers taps. It pauses over form fields and while text is selected, keeps the normal mouse cursor, and is off for visitors who ask their device for reduced motion. The admin panel never shows it.') }}</p>
<div class="form-inline">
    <div class="field"><label for="pointer-effect">{{ __('Effect') }}</label>
        <select id="pointer-effect" name="pointer_effect">
            <option value="default" @selected(old('pointer_effect', $pointer['choice']) === 'default')>{{ __('Theme effect: :name', ['name' => __('pointer.'.$pointer['default'])]) }}</option>
            @foreach($pointerEffects as $pointerEffect)<option value="{{ $pointerEffect }}" @selected(old('pointer_effect', $pointer['choice']) === $pointerEffect)>{{ __('pointer.'.$pointerEffect) }}</option>@endforeach
            <option value="off" @selected(old('pointer_effect', $pointer['choice']) === 'off')>{{ __('Off') }}</option>
        </select>
    </div>
    <div class="field"><label for="pointer-strength">{{ __('Strength') }}</label>
        <select id="pointer-strength" name="pointer_strength">
            @foreach($pointerStrengths as $pointerStrength)<option value="{{ $pointerStrength }}" @selected(old('pointer_strength', $pointer['strength']) === $pointerStrength)>{{ __('pointer.strength.'.$pointerStrength) }}</option>@endforeach
        </select>
    </div>
    <label class="checkbox" for="pointer-touch"><input type="hidden" name="pointer_touch" value="0"><input id="pointer-touch" type="checkbox" name="pointer_touch" value="1" @checked(old('pointer_touch', $pointer['touch']))><span>{{ __('Tap effects on phones and tablets') }}</span></label>
</div>
<div class="pointer-preview" data-pointer-preview data-pointer-default="{{ $pointer['default'] }}"><p>{{ __('Move the mouse here, click, or tap to try the effect. Changes show at once; save to use them on the site.') }}</p></div>
</fieldset>
<fieldset><legend>{{ __('Hide from the main menu') }}</legend>
@foreach(\App\Core\Theme\HomeLayout::NAV_KEYS as $navKey)
<label class="checkbox" for="nav-{{ $navKey }}"><input id="nav-{{ $navKey }}" name="nav_hidden[]" type="checkbox" value="{{ $navKey }}" @checked(in_array($navKey, $navHidden, true))><span>{{ __('navkey.'.$navKey) }}</span></label>
@endforeach
</fieldset>
<div class="form-actions"><button class="button" type="submit">{{ __('Save changes') }}</button></div>
</form>
</div>
@endsection
