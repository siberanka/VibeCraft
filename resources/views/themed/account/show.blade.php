@extends('theme::layouts.app')
@section('title', __('My account'))
@section('content')
@php($accountUser = $user ?? auth()->user())
<div class="wrap page"><header class="page-head"><h1>{{ __('My account') }}</h1></header>@include('theme::account.nav')@include('theme::partials.errors')
<div class="split"><div class="stack">
    <section class="panel">
        <h2>{{ __('Profile') }}</h2>
        <form method="post" action="{{ url('/user/profile-information') }}" class="form" data-submit-once>
            @csrf @method('PUT')
            <div class="field"><label for="account-name">{{ __('Username') }}</label><input id="account-name" value="{{ $accountUser->name }}" readonly autocomplete="nickname"><p class="hint">{{ __('Your username is permanent.') }}</p></div>
            <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email', $accountUser->email) }}" required maxlength="191" autocomplete="email"></div>
            <div class="field"><label for="profile-current-password">{{ __('Current password') }}</label><input id="profile-current-password" name="current_password" type="password" required autocomplete="current-password"></div>
            <div class="form-actions"><button class="button" type="submit">{{ __('Save profile') }}</button></div>
        </form>
    </section>
    <section class="panel"><h2>{{ __('Linked Minecraft accounts') }}</h2>
        @forelse($identities ?? $accountUser->identities as $identity)
            <div class="identity-row">
                <div><strong>{{ $identity->username }}</strong><small>{{ ucfirst($identity->edition) }} — {{ __('Verified in game') }}</small></div>
                <form method="post" action="{{ route('account.unlink', $identity->edition) }}" data-confirm="{{ __('Unlink this Minecraft account?') }}">@csrf @method('DELETE')<button class="text-button small" type="submit">{{ __('Unlink account') }}</button></form>
            </div>
        @empty<p class="muted">{{ __('You have not linked a Minecraft account yet.') }}</p>@endforelse
        <p class="small muted form-footer">{{ __('Generate a temporary code, then use it on one of our connected game servers.') }}</p>
        @if($linkCode ?? session('link_code'))<div class="alert"><p>{{ __('Your temporary linking code') }}</p><strong class="security-code">{{ $linkCode ?? session('link_code') }}</strong><p class="small muted">{{ __('Enter /vclink followed by this code in game. The code expires after 10 minutes.') }}</p></div>@endif
        <form method="post" action="{{ route('account.link') }}" class="form-actions" data-submit-once>@csrf<button class="button button-quiet" type="submit">{{ __('Generate linking code') }}</button></form>
    </section>
</div><aside class="stack">
    <section class="panel"><h2>{{ $site['wallet_label'] }}</h2><p class="product-price">{{ \App\Support\Money::format(($wallet ?? $accountUser->wallet)?->balance_minor ?? 0) }}</p>
        @if(!empty($topupGateways))<form id="topup" method="get" action="{{ route('account.topup.review') }}" class="form form-footer" data-submit-once>@if($creditPackages->isNotEmpty())<fieldset class="package-options"><legend>{{ __('Packages') }}</legend>@foreach($creditPackages as $creditPackage)<label class="package-option"><input type="radio" name="package" value="{{ $creditPackage->id }}" @checked($loop->first)><span class="package-name">{{ $creditPackage->name }}</span><strong>{{ \App\Support\Money::format($creditPackage->price_minor) }}</strong>@if($creditPackage->bonus_minor > 0)<span class="package-bonus">+{{ \App\Support\Money::format($creditPackage->bonus_minor) }} {{ __('bonus') }}</span>@endif</label>@endforeach</fieldset>@endif<div class="field"><label for="amount">{{ $creditPackages->isNotEmpty() ? __('Or enter an amount') : __('Amount') }} ({{ \App\Support\Money::currency() }})</label><input id="amount" name="amount" inputmode="decimal" autocomplete="off" @if($creditPackages->isEmpty()) required @endif maxlength="15" placeholder="10,00"></div><div class="field"><label for="topup-method">{{ __('Payment method') }}</label><select id="topup-method" name="method" required>@foreach($topupGateways as $gateway)<option value="{{ $gateway->id() }}">{{ $gateway->label() }}</option>@endforeach</select></div><button class="button" type="submit">{{ __('Review top-up') }}</button></form>@endif
    </section>
    @include('theme::partials.active-ranks')
    @include('theme::partials.discord-link')
    @if($site['modules']['store'] ?? false)<section class="panel" id="gift-code" aria-labelledby="gift-code-heading"><h2 id="gift-code-heading">{{ __('Redeem a code') }}</h2>
        <form method="post" action="{{ route('account.gift-code') }}" class="form" data-submit-once>@csrf<div class="field"><label for="gift-code-input">{{ __('Code') }}</label><input id="gift-code-input" name="code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="40" placeholder="XXXX-XXXX-XXXX-XXXX" required>@error('code')<p class="field-error" role="alert">{{ $message }}</p>@enderror</div>@include('theme::partials.captcha', ['captchaForm' => 'gift_code'])<button class="button" type="submit">{{ __('Redeem') }}</button></form>
    </section>@endif
    <section class="panel"><h2>{{ __('Need a hand?') }}</h2><div class="form-actions"><a href="{{ route('orders.index') }}">{{ __('View my orders') }}</a></div></section>
</aside></div></div>
@endsection
