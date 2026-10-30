@extends('theme::layouts.app')
@section('title', __('Register'))
@section('content')
<div class="wrap page"><div class="auth-shell">
    <div class="auth-intro">@include('theme::partials.world-scene', ['sceneClass' => 'auth-world', 'sceneSizes' => '(max-width:760px) 96px, 600px'])<h1>{{ __('Make yourself at home.') }}</h1><p>{{ __('Create your community account, then securely link your Minecraft identity.') }}</p>@include('theme::partials.connect')</div>
    <section class="auth-form"><h2>{{ __('Create an account') }}</h2>@include('theme::partials.errors')
        @include('theme::partials.legal-country', ['legalFormAction' => route('register')])
        @if($legalSelection)
        <form action="{{ route('register') }}" method="post" class="form" data-submit-once>@csrf
            <div class="field"><label for="name">{{ __('Username') }}</label><input id="name" name="name" value="{{ old('name') }}" required autocomplete="nickname" minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autofocus aria-describedby="username-hint"><p class="hint" id="username-hint">{{ __('Use letters, numbers and underscores. Your username cannot be changed later.') }}</p></div>
            <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" maxlength="191"></div>
            <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" aria-describedby="password-hint"><p id="password-hint" class="hint">{{ __('Use at least 12 characters, including letters and numbers.') }}</p></div>
            <div class="field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></div>
            @include('theme::partials.captcha', ['captchaForm' => 'register'])
            @include('theme::partials.legal-agreement')
            <button class="button" type="submit">{{ __('Create an account') }}</button>
        </form>
        @endif
        <p class="form-footer">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a></p>
    </section>
</div></div>
@endsection
