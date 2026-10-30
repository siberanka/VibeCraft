<a class="nav-link" href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'home']) <span>{{ __('Home') }}</span></a>
@unless(in_array('servers', $site['nav_hidden'] ?? [], true))<a class="nav-link" href="{{ route('servers.index') }}" @if(request()->routeIs('servers.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'server']) <span>{{ __('Servers') }}</span></a>@endunless
@if(($site['modules']['store'] ?? true) && ! in_array('store', $site['nav_hidden'] ?? [], true))
    <a class="nav-link nav-store" href="{{ route('store.index') }}" @if(request()->routeIs('store.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'store']) <span>{{ __('Store') }}</span></a>
    @auth<a class="nav-link" href="{{ route('cart.index') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'cart']) <span>{{ __('Cart') }}</span></a>@endauth
@endif
@if(($site['modules']['news'] ?? true) && ! in_array('news', $site['nav_hidden'] ?? [], true))<a class="nav-link" href="{{ route('news.index') }}" @if(request()->routeIs('news.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'news']) <span>{{ __('News') }}</span></a>@endif
@if(($site['modules']['tickets'] ?? true) && ! in_array('support', $site['nav_hidden'] ?? [], true))<a class="nav-link" href="{{ route('tickets.index') }}" @if(request()->routeIs('tickets.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'support']) <span>{{ __('Support') }}</span></a>@endif
@unless(in_array('topup', $site['nav_hidden'] ?? [], true))@include('theme::partials.nav-topup')@endunless
@foreach($site['menus']['header'] ?? [] as $menuItem)@include('theme::partials.menu-link', ['menuItem' => $menuItem, 'menuClass' => 'nav-link', 'menuIcon' => true])@endforeach
