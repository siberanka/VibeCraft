@php
    $portalSection = match (true) {
        request()->routeIs('store.*', 'cart.*', 'checkout.*') => ['Store', 'store', 'store.index'],
        request()->routeIs('account.*') => ['My account', 'account', 'account.show'],
        request()->routeIs('orders.*') => ['Orders', 'receipt', 'orders.index'],
        request()->routeIs('tickets.*') => ['Support', 'support', 'tickets.index'],
        request()->routeIs('servers.*') => ['Servers', 'server', 'servers.index'],
        request()->routeIs('news.*') => ['News', 'news', 'news.index'],
        request()->routeIs('wiki.*') => ['Wiki', 'news', 'wiki.index'],
        request()->routeIs('admin.*') => ['Administration', 'settings', 'admin.dashboard'],
        request()->routeIs('pages.*') => ['Community', 'compass', null],
        default => null,
    };
@endphp
@if($portalSection)
<nav class="wrap portal-path" aria-label="{{ __('Breadcrumb') }}">
    <a href="{{ route('home') }}">@include('theme::partials.brand-voxel')<span>{{ $site['name'] }}</span></a>
    @include('theme::partials.icon', ['icon' => 'chevron'])
    @if($portalSection[2] && !request()->routeIs($portalSection[2]))<a href="{{ route($portalSection[2]) }}">@include('theme::partials.icon', ['icon' => $portalSection[1]]) {{ __($portalSection[0]) }}</a>
    @else<span @if($portalSection[2]) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => $portalSection[1]]) {{ __($portalSection[0]) }}</span>@endif
</nav>
@endif
