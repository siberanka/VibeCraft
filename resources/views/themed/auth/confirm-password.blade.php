@extends('theme::layouts.app')
@section('title', __('Confirm your password'))
@section('content')
<div class="wrap page"><div class="auth-shell"><div class="auth-intro">@include('theme::partials.world-scene', ['sceneClass' => 'auth-world', 'sceneSizes' => '(max-width:760px) 96px, 600px'])<h1>{{ __('Keep your account protected.') }}</h1><p>{{ __('This action needs a recent password confirmation.') }}</p></div><section class="auth-form"><h2>{{ __('Confirm your password') }}</h2>@include('theme::partials.errors')<form method="post" action="{{ route('password.confirm') }}" class="form" data-submit-once>@csrf<div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus></div><button class="button" type="submit">{{ __('Confirm') }}</button></form></section></div></div>
@endsection
