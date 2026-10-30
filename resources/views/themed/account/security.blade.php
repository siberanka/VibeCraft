@extends('theme::layouts.app')
@section('title', __('Account security'))
@section('content')
@php($accountUser = $user ?? auth()->user())
<div class="wrap page"><header class="page-head"><h1>{{ __('Account security') }}</h1><p>{{ __('Protect your login and keep recovery access somewhere safe.') }}</p></header>@include('theme::account.nav')@include('theme::partials.errors')<div class="split">
<section class="panel"><h2>{{ __('Change password') }}</h2><form action="{{ url('/user/password') }}" method="post" class="form" data-submit-once>@csrf @method('PUT')<div class="field"><label for="current_password">{{ __('Current password') }}</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div><div class="field"><label for="password">{{ __('New password') }}</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"></div><div class="field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div><button class="button" type="submit">{{ __('Update password') }}</button></form></section>
<section class="panel"><h2>{{ __('Two-factor authentication') }}</h2>
@if($accountUser->hasTwoFactorEnabled())
    <p class="status-online">{{ __('Two-factor authentication is enabled.') }}</p><p class="small muted">{{ __('Save your recovery codes offline. Each code can be used once.') }}</p>
    @if(!empty($recoveryCodes))<ul class="recovery-codes">@foreach($recoveryCodes as $recoveryCode)<li><code>{{ $recoveryCode }}</code></li>@endforeach</ul>@endif
    <div class="stack form-footer"><form method="post" action="{{ url('/user/two-factor-recovery-codes') }}" data-confirm="{{ __('Replace all existing recovery codes?') }}">@csrf<button class="button button-quiet" type="submit">{{ __('Regenerate recovery codes') }}</button></form><form method="post" action="{{ url('/user/two-factor-authentication') }}" data-confirm="{{ __('Disable two-factor authentication?') }}">@csrf @method('DELETE')<button class="button button-danger" type="submit">{{ __('Disable two-factor authentication') }}</button></form></div>
@elseif($accountUser->two_factor_secret)
    <p class="muted small">{{ __('Scan the QR code with your authenticator, then enter its current code.') }}</p>
    @if(!empty($qrCode))<div class="qr-code">{!! $qrCode !!}</div>@endif
    <form method="post" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="form" data-submit-once>@csrf<div class="field"><label for="code">{{ __('Authentication code') }}</label><input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div><button class="button" type="submit">{{ __('Confirm two-factor authentication') }}</button></form>
@else
    <p class="muted">{{ __('Use an authenticator app to add a second step to every login.') }}</p><form method="post" action="{{ url('/user/two-factor-authentication') }}" class="form-actions">@csrf<button class="button" type="submit">{{ __('Enable two-factor authentication') }}</button></form>
@endif
</section></div></div>
@endsection
