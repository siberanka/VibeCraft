@php($adminMenu = \App\Core\AdminNavigation::for(request(), $themeId ?? 'atlas'))
<nav class="admin-nav" aria-label="{{ __('Administration navigation') }}">
    <div class="admin-areas">
        @foreach($adminMenu['areas'] as $adminArea)
        <a class="admin-area" href="{{ $adminArea['url'] }}" @if($adminArea['current']) aria-current="true" @endif>{{ __($adminArea['label']) }}</a>
        @endforeach
    </div>
    @if($adminMenu['pages'] !== [])
    <div class="admin-pages">
        @foreach($adminMenu['pages'] as $adminPage)
        <a href="{{ $adminPage['url'] }}" @if($adminPage['current']) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => $adminPage['icon']]) <span>{{ __($adminPage['label']) }}</span></a>
        @endforeach
    </div>
    @endif
</nav>
