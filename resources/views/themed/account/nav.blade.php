<nav class="side-nav" aria-label="{{ __('Account navigation') }}">
<a href="{{ route('account.show') }}" @if(request()->routeIs('account.show')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'account']) <span>{{ __('Profile and game accounts') }}</span></a>
<a href="{{ route('account.security') }}" @if(request()->routeIs('account.security')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'shield']) <span>{{ __('Security') }}</span></a>
<a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'receipt']) <span>{{ __('Orders') }}</span></a>
@if($site['modules']['tickets'])<a href="{{ route('tickets.index') }}">@include('theme::partials.icon', ['icon' => 'support']) <span>{{ __('Support tickets') }}</span></a>@endif
</nav>
