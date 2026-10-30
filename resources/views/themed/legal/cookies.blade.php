@extends('theme::layouts.app')
@section('title', __('Cookies and preferences'))
@section('content')
<div class="wrap page legal-portal">
    <header class="page-head"><h1>{{ __('Cookies and preferences') }}</h1><p>{{ __('Your session, security and optional choices should be clear.') }}</p></header>
    <section class="legal-cookie-info"><h2>{{ __('Application session and security') }}</h2><dl>
        <div><dt>{{ $sessionCookie }}</dt><dd>{{ __('Keeps your session, language choice and form state connected to your browser.') }}</dd></div>
        <div><dt>XSRF-TOKEN</dt><dd>{{ __('Protects forms against requests from another site.') }}</dd></div>
        <div><dt>{{ $rememberCookie }}</dt><dd>{{ __('Used when you choose to stay signed in on this browser.') }}</dd></div>
    </dl></section>
    <p>{{ __('These application cookies support the features you request. Hosted security services and embedded content must also be described in the operator’s published cookie notice.') }}</p>
    <div class="form-actions"><a class="button" href="{{ route('legal.index', ['kind' => 'cookies']) }}">{{ __('Read cookie notice') }}</a><a class="button button-quiet" href="{{ route('legal.index', ['kind' => 'privacy']) }}">{{ __('Privacy information') }}</a></div>
</div>
@endsection
