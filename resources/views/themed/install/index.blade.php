@extends('theme::layouts.app')
@section('title', __('Set up your community'))
@section('content')
<div class="wrap page">
    <header class="page-head"><h1>{{ __('Set up your community.') }}</h1><p>{{ __('Three short steps: prove you own this hosting account, connect the database and create the owner account.') }}</p></header>
    @include('theme::partials.errors')
    <ol class="install-steps">
        <li @if($step === 'token') aria-current="step" @endif>{{ __('Setup token') }}</li>
        <li @if($step === 'database') aria-current="step" @endif>{{ __('Database') }}</li>
        <li @if($step === 'owner') aria-current="step" @endif>{{ __('Owner account') }}</li>
    </ol>
    <div class="split">
        <section class="panel">
            @if($step === 'token')
                <h2>{{ __('Setup token') }}</h2>
                <p>{{ __('A one-time token was written to your hosting account. Open your file manager, view this file and paste its content here:') }}</p>
                <p><code>{{ $tokenPath }}</code></p>
                <p class="hint">{{ __('Only someone with access to the hosting files can read it, so nobody else can take over a fresh installation. The file is deleted when setup completes.') }}</p>
                <form action="{{ route('install.token') }}" method="post" class="form" data-submit-once>@csrf
                    <div class="field"><label for="setup_token">{{ __('Setup token') }}</label><input id="setup_token" name="setup_token" type="password" autocomplete="off" required minlength="64" maxlength="64"></div>
                    <button class="button" type="submit" @disabled(!$ready)>{{ __('Continue') }}</button>
                </form>
            @elseif($step === 'database')
                <h2>{{ __('Database') }}</h2>
                <p>{{ __('Create an empty MySQL or MariaDB database and a user for it in your hosting panel, then enter the details below. The tables are created automatically.') }}</p>
                <form action="{{ route('install.database') }}" method="post" class="form" data-submit-once>@csrf
                    <div class="field"><label for="app_url">{{ __('Site address') }}</label><input id="app_url" name="app_url" type="url" value="{{ old('app_url', $suggestedUrl) }}" required maxlength="255" aria-describedby="app-url-hint"><p class="hint" id="app-url-hint">{{ __('The exact address players will open, for example https://play.example.com. Use https.') }}</p></div>
                    <div class="field"><label for="db_host">{{ __('Database host') }}</label><input id="db_host" name="db_host" value="{{ old('db_host', 'localhost') }}" required maxlength="253"></div>
                    <div class="field"><label for="db_port">{{ __('Port') }}</label><input id="db_port" name="db_port" type="number" value="{{ old('db_port', 3306) }}" required min="1" max="65535"></div>
                    <div class="field"><label for="db_database">{{ __('Database name') }}</label><input id="db_database" name="db_database" value="{{ old('db_database') }}" required maxlength="64" autocomplete="off"></div>
                    <div class="field"><label for="db_username">{{ __('Database user') }}</label><input id="db_username" name="db_username" value="{{ old('db_username') }}" required maxlength="80" autocomplete="off"></div>
                    <div class="field"><label for="db_password">{{ __('Database password') }}</label><input id="db_password" name="db_password" type="password" maxlength="200" autocomplete="new-password"></div>
                    <button class="button" type="submit" @disabled(!$ready)>{{ __('Connect and create tables') }}</button>
                </form>
            @else
                <h2>{{ __('Owner account') }}</h2>
                <form action="{{ url('/install') }}" method="post" class="form" data-submit-once>@csrf
                    <div class="field"><label for="site_name">{{ __('Community name') }}</label><input id="site_name" name="site_name" value="{{ old('site_name') }}" required maxlength="80"></div>
                    <div class="field"><label for="name">{{ __('Owner name') }}</label><input id="name" name="name" value="{{ old('name') }}" required maxlength="24" autocomplete="username"></div>
                    <div class="field"><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email"></div>
                    <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"></div>
                    <div class="field"><label for="password_confirmation">{{ __('Confirm password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></div>
                    <button class="button" type="submit" @disabled(!$ready)>{{ __('Complete setup') }}</button>
                </form>
                <p class="hint">{{ __('After setup, add this cron job in your hosting panel (every minute):') }}</p>
                <p><code>{{ $cronLine }}</code></p>
            @endif
        </section>
        <aside>
            <h2>{{ __('Hosting checks') }}</h2>
            <ul class="install-checks">@foreach($checks as $checkLabel => $checkPassed)<li><span>{{ __($checkLabel) }}</span><strong class="{{ $checkPassed ? 'status-online' : 'field-error' }}">{{ $checkPassed ? __('Ready') : __('Needs attention') }}</strong></li>@endforeach</ul>
            @if($missingExtensions !== [])<p class="field-error">{{ __('Enable these PHP extensions in your hosting panel:') }} {{ implode(', ', $missingExtensions) }}</p>@endif
            <p class="small muted">{{ __('Select PHP 8.4 or newer for this site in your hosting panel (for example MultiPHP Manager in cPanel). Complete every check before continuing.') }}</p>
        </aside>
    </div>
</div>
@endsection
