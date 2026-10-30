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
<fieldset><legend>{{ __('Hide from the main menu') }}</legend>
@foreach(\App\Core\Theme\HomeLayout::NAV_KEYS as $navKey)
<label class="checkbox" for="nav-{{ $navKey }}"><input id="nav-{{ $navKey }}" name="nav_hidden[]" type="checkbox" value="{{ $navKey }}" @checked(in_array($navKey, $navHidden, true))><span>{{ __('navkey.'.$navKey) }}</span></label>
@endforeach
</fieldset>
<div class="form-actions"><button class="button" type="submit">{{ __('Save changes') }}</button><a href="{{ route('admin.themes.tokens', $theme) }}">{{ __('Theme colours') }}</a><a href="{{ route('admin.menus.index') }}">{{ __('Menus') }}</a><a href="{{ route('admin.custom-css') }}">{{ __('Custom CSS') }}</a></div>
</form>
</div>
@endsection
