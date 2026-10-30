@forelse(collect($publicServers ?? [])->take($serverLimit ?? 4) as $server)
<div class="server-line">
    <div><strong>{{ $server->name }}</strong>@if($server->status_version)<small>{{ $server->status_version }}</small>@endif</div>
    @if(!$server->status_checked_at)<span class="status-label status-unknown">{{ __('Not checked') }}</span>
    @elseif($server->status_online)<span class="status-label status-online">{{ $server->status_players }} / {{ $server->status_max_players }} {{ __('online') }}</span>
    @else<span class="status-label status-offline">{{ __('Offline') }}</span>@endif
</div>
@empty
    <p class="muted small">{{ __('No public servers have been added yet.') }}</p>
@endforelse
