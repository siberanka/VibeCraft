@extends('theme::layouts.app')
@section('title', __($title ?? 'Site settings'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __($title ?? 'Site settings') }}</h1><p>{{ __('Configure your community and the services it uses.') }}</p></header>@include('theme::admin.nav')@include('theme::partials.errors')
<div class="split"><form method="post" action="{{ $action ?? route('admin.settings.update') }}" class="form" data-submit-once>@csrf @method('PUT')
@foreach($settings ?? [] as $settingKey => $settingValue)
    @php($settingId = 'setting-'.str_replace('.', '-', $settingKey))
    @if(str_ends_with($settingKey, '_media_id'))
        @include('theme::partials.media-picker', ['pickerName' => 'settings['.$settingKey.']', 'pickerError' => 'settings.'.$settingKey, 'pickerId' => $settingId, 'pickerLabel' => __($settingKey), 'pickerValue' => old('settings.'.$settingKey, $settingValue)])
        @continue
    @endif
    <div class="field"><label for="{{ $settingId }}">{{ __($settingKey) }}</label>
    @if(is_bool($settingValue))<select id="{{ $settingId }}" name="settings[{{ $settingKey }}]"><option value="1" @selected($settingValue)>{{ __('Enabled') }}</option><option value="0" @selected(!$settingValue)>{{ __('Disabled') }}</option></select>
    @elseif(is_array($settingValue))<input id="{{ $settingId }}" name="settings[{{ $settingKey }}]" value="{{ implode(',', $settingValue) }}"><p class="hint">{{ __('Separate values with commas.') }}</p>
    @elseif(in_array($settingKey, $secretKeys ?? [], true))<input id="{{ $settingId }}" type="password" name="settings[{{ $settingKey }}]" autocomplete="new-password" value=""><p class="hint">{{ __('Leave blank to keep the current secret.') }}</p>
    @elseif(str_contains($settingKey, 'description') || str_contains($settingKey, 'footer_note'))<textarea id="{{ $settingId }}" name="settings[{{ $settingKey }}]" rows="4">{{ old('settings.'.$settingKey, $settingValue) }}</textarea>
    @else<input id="{{ $settingId }}" name="settings[{{ $settingKey }}]" value="{{ old('settings.'.$settingKey, $settingValue) }}" type="{{ is_int($settingValue) ? 'number' : 'text' }}">@endif
    </div>
@endforeach
<div class="form-actions"><button class="button" type="submit">{{ __('Save settings') }}</button></div></form><aside><h2>{{ __('Your configuration') }}</h2><p class="muted form-footer">{{ __('Only enable integrations after their credentials and delivery settings have been verified.') }}</p></aside></div></div>
@endsection
