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
<fieldset><legend>{{ __('Hide from the main menu') }}</legend>
@foreach(\App\Core\Theme\HomeLayout::NAV_KEYS as $navKey)
<label class="checkbox" for="nav-{{ $navKey }}"><input id="nav-{{ $navKey }}" name="nav_hidden[]" type="checkbox" value="{{ $navKey }}" @checked(in_array($navKey, $navHidden, true))><span>{{ __('navkey.'.$navKey) }}</span></label>
@endforeach
</fieldset>
<div class="form-actions"><button class="button" type="submit">{{ __('Save changes') }}</button></div>
</form>
</div>
@endsection
