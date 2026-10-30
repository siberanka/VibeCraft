@extends('theme::layouts.app')
@section('title', __('Theme colours'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $name }} — {{ __('Theme colours') }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
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
<form method="post" action="{{ route('admin.themes.tokens.reset', $theme) }}" class="form-actions section" data-submit-once>@csrf<button class="button button-quiet" type="submit">{{ __('Restore theme defaults') }}</button></form>
</div>
@endsection
