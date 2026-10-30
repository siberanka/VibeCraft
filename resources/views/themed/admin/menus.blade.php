@extends('theme::layouts.app')
@section('title', __('Menus'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Menus') }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
<div class="split">
<form method="post" action="{{ route('admin.menus.store') }}" class="form" data-submit-once>@csrf
    <h2>{{ __('Add link') }}</h2>
    <div class="field"><label for="menu-location">{{ __('Menu') }}</label><select id="menu-location" name="location"><option value="header" @selected(old('location') === 'header')>{{ __('Header menu') }}</option><option value="footer" @selected(old('location') === 'footer')>{{ __('Footer menu') }}</option></select></div>
    <div class="field"><label for="menu-label">{{ __('Label') }}</label><input id="menu-label" name="label" maxlength="40" value="{{ old('label') }}" required></div>
    <div class="field"><label for="menu-url">{{ __('Link') }}</label><input id="menu-url" name="url" maxlength="255" value="{{ old('url') }}" placeholder="/rules" spellcheck="false" required><p class="hint">{{ __('Use a site path starting with / or an https:// address.') }}</p></div>
    <div class="field"><label for="menu-sort">{{ __('Position') }}</label><input id="menu-sort" name="sort" type="number" min="0" max="1000" value="{{ old('sort', 0) }}"></div>
    <label class="checkbox" for="menu-tab"><input id="menu-tab" name="new_tab" type="checkbox" value="1" @checked(old('new_tab'))><span>{{ __('Open in a new tab') }}</span></label>
    <div class="form-actions"><button class="button" type="submit">{{ __('Add link') }}</button></div>
</form>
<div class="flow">
@foreach(['header' => $header, 'footer' => $footer] as $menuLocation => $menuItems)
<section class="panel" aria-labelledby="menu-{{ $menuLocation }}"><h2 id="menu-{{ $menuLocation }}">{{ $menuLocation === 'header' ? __('Header menu') : __('Footer menu') }}</h2>
@forelse($menuItems as $item)
<form method="post" action="{{ route('admin.menus.update', $item) }}" class="form form-inline" data-submit-once>@csrf @method('PUT')
    <div class="field"><label for="label-{{ $item->id }}">{{ __('Label') }}</label><input id="label-{{ $item->id }}" name="label" maxlength="40" value="{{ $item->label }}" required></div>
    <div class="field"><label for="url-{{ $item->id }}">{{ __('Link') }}</label><input id="url-{{ $item->id }}" name="url" maxlength="255" value="{{ $item->url }}" spellcheck="false" required></div>
    <div class="field"><label for="sort-{{ $item->id }}">{{ __('Position') }}</label><input id="sort-{{ $item->id }}" name="sort" type="number" min="0" max="1000" value="{{ $item->sort }}"></div>
    <label class="checkbox" for="visible-{{ $item->id }}"><input id="visible-{{ $item->id }}" name="is_visible" type="checkbox" value="1" @checked($item->is_visible)><span>{{ __('Visible') }}</span></label>
    <label class="checkbox" for="tab-{{ $item->id }}"><input id="tab-{{ $item->id }}" name="new_tab" type="checkbox" value="1" @checked($item->new_tab)><span>{{ __('Open in a new tab') }}</span></label>
    <button class="button button-small" type="submit">{{ __('Save changes') }}</button>
</form>
<form method="post" action="{{ route('admin.menus.destroy', $item) }}" class="form-actions" data-submit-once>@csrf @method('DELETE')<button class="button button-quiet button-small" type="submit">{{ __('Remove :label', ['label' => $item->label]) }}</button></form>
@empty
<p class="muted">{{ __('No links yet.') }}</p>
@endforelse
</section>
@endforeach
</div>
</div>
</div>
@endsection
