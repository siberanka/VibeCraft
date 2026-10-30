@auth
<details class="portal-menu account-menu" data-disclosure data-account-menu>
    <summary>@include('theme::partials.icon', ['icon' => 'account'])<span class="menu-owner">{{ auth()->user()->name }}</span>@include('theme::partials.icon', ['icon' => 'chevron', 'iconClass' => 'menu-chevron'])</summary>
    <div class="menu-panel">
        <a href="{{ route('account.show') }}" @if(request()->routeIs('account.show')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'account']) {{ __('My account') }}</a>
        @php($unreadNotices = auth()->user()->unreadNotifications()->count())
        <a href="{{ route('account.notifications') }}" @if(request()->routeIs('account.notifications')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'clock']) {{ __('Notifications') }}@if($unreadNotices > 0) <span class="news-chip">{{ $unreadNotices > 9 ? '9+' : $unreadNotices }}</span>@endif</a>
        <a href="{{ route('account.security') }}" @if(request()->routeIs('account.security')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'shield']) {{ __('Security') }}</a>
        <a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'receipt']) {{ __('Orders') }}</a>
        @if($site['modules']['tickets'])<a href="{{ route('tickets.index') }}">@include('theme::partials.icon', ['icon' => 'support']) {{ __('Support tickets') }}</a>@endif
        @if(auth()->user()->isStaff())<a href="{{ route('admin.dashboard') }}">@include('theme::partials.icon', ['icon' => 'settings']) {{ __('Administration') }}</a>@endif
        <form method="post" action="{{ route('logout') }}" data-submit-once>@csrf<button class="menu-action text-button" type="submit">@include('theme::partials.icon', ['icon' => 'exit']) {{ __('Log out') }}</button></form>
    </div>
</details>
@else
<div class="session-actions">
    <a class="session-login" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'account']) {{ __('Log in') }}</a>
    @if($site['registration'] ?? true)<a class="button button-small" href="{{ route('register') }}">{{ __('Register') }}</a>@endif
</div>
@endauth
