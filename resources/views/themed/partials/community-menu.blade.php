@if(true)
<details class="portal-menu community-menu" data-disclosure data-community-menu>
    <summary>@include('theme::partials.icon', ['icon' => 'compass']) {{ __('Community') }} @include('theme::partials.icon', ['icon' => 'chevron', 'iconClass' => 'menu-chevron'])</summary>
    <div class="menu-panel">
        @if(!empty($site['modules']['wiki']))<a href="{{ route('wiki.index') }}" @if(request()->routeIs('wiki.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'news']) {{ __('Wiki') }}</a>@endif
        @foreach($navPages ?? [] as $navPage)<a href="{{ route('pages.show', $navPage) }}" @if(request()->routeIs('pages.show') && request()->route('page')?->id === $navPage->id) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'news']) {{ $navPage->title }}</a>@endforeach
        <a href="{{ route('forum.index') }}" @if(request()->routeIs('forum.*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'support']) {{ __('Forum') }}</a>
        <a href="{{ route('community.leaderboards') }}" @if(request()->routeIs('community.leaderboards')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'gem']) {{ __('Leaderboards') }}</a>
        <a href="{{ route('community.punishments') }}" @if(request()->routeIs('community.punishments')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'shield']) {{ __('Punishments') }}</a>
        <a href="{{ route('community.vote') }}" @if(request()->routeIs('community.vote')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'check']) {{ __('Vote') }}</a>
        <a href="{{ route('community.positions') }}" @if(request()->routeIs('community.positions', 'community.apply*')) aria-current="page" @endif>@include('theme::partials.icon', ['icon' => 'account']) {{ __('Staff applications') }}</a>
        @if(!empty($site['discord']))<a href="{{ $site['discord'] }}" rel="noopener noreferrer">@include('theme::partials.icon', ['icon' => 'support']) Discord</a>@endif
    </div>
</details>
@endif
