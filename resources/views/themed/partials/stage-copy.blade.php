<div class="stage-copy {{ $copyClass ?? '' }}">
    <h1 id="home-heading" class="stage-title">{{ $site['hero_title'] ?: $site['name'] }}</h1>
    @if($site['description'] ?: $site['tagline'])<p class="stage-lede">{{ $site['description'] ?: $site['tagline'] }}</p>@endif
    <div class="stage-join">@include('theme::partials.connect')</div>
    <div class="stage-actions">
        @if(!empty($site['discord']))<a class="button button-quiet" href="{{ $site['discord'] }}" rel="noopener noreferrer">{{ __('Join Discord') }}</a>
        @elseif($site['modules']['store'])<a class="button button-quiet" href="{{ route('store.index') }}">{{ __('Store') }}</a>
        @else<a class="button button-quiet" href="{{ route('servers.index') }}">{{ __('Servers') }}</a>@endif
    </div>
</div>
