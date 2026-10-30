@extends('theme::layouts.app')
@section('title', __('Log in'))
@section('content')
<div class="wrap page"><div class="auth-shell">
    <div class="auth-intro">@include('theme::partials.world-scene', ['sceneClass' => 'auth-world', 'sceneSizes' => '(max-width:760px) 96px, 600px'])<h1>{{ __('Welcome back.') }}</h1>@include('theme::partials.connect')</div>
    <section class="auth-form"><h2>{{ __('Log in to your account') }}</h2>@include('theme::partials.errors')
        <form action="{{ route('login') }}" method="post" class="form" data-submit-once>@csrf
            <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="254" autofocus @error('email') aria-invalid="true" @enderror></div>
            <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <label class="checkbox"><input type="checkbox" name="remember" value="1" @checked(old('remember'))><span>{{ __('Remember me on this device') }}</span></label>
            @include('theme::partials.captcha', ['captchaForm' => 'login'])
            <button class="button" type="submit">{{ __('Log in') }}</button>
            <a class="small" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
        </form>
        @if($site['registration'])<p class="form-footer">{{ __('New to the community?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a></p>@endif
    </section>
</div></div>
@endsection
