@php($worldList = collect($publicServers ?? [])->take($worldLimit ?? 3))
@if($worldList->isNotEmpty())
<div class="world-cards">
    @foreach($worldList as $server)
    <article class="world-card" id="world-{{ $server->id }}">
        <div class="world-card-head">
            @include('theme::partials.voxel-emblem', ['emblem' => ['gateway', 'waystone', 'shield'][$loop->index % 3], 'emblemClass' => 'world-emblem'])
            <div><h3>{{ $server->name }}</h3>@if($server->description)<p>{{ $server->description }}</p>@endif</div>
        </div>
        @if(!$server->status_checked_at)
            <span class="status-label status-unknown">{{ __('Not checked') }}</span>
        @elseif($server->status_online)
            @php($fill = $server->status_max_players ? min(100, (int) round(($server->status_players / max(1, $server->status_max_players)) * 100)) : 0)
            <div class="world-load"><span class="status-label status-online">{{ $server->status_players }} / {{ $server->status_max_players }} {{ __('online') }}</span><span class="world-meter" aria-hidden="true"><span class="world-meter-fill fill-{{ intdiv($fill, 10) }}"></span></span></div>
        @else
            <span class="status-label status-offline">{{ __('Offline') }}</span>
        @endif
        <a class="world-link" href="{{ route('servers.index') }}#server-{{ $server->id }}">{{ __('Join server') }}</a>
    </article>
    @endforeach
</div>
@endif
