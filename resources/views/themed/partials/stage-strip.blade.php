@php
    $stripServers = collect($publicServers ?? []);
    $stripMeasured = $stripServers->contains(fn ($server) => $server->status_checked_at !== null);
@endphp
<div class="stage-strip" role="list">
    @if($stripMeasured)
    <div class="strip-cell strip-online" role="listitem">
        @include('theme::partials.voxel-emblem', ['emblem' => 'lantern', 'emblemClass' => 'strip-emblem'])
        <span class="strip-copy"><strong class="strip-number">{{ number_format((int) ($onlineTotal ?? 0)) }}</strong><span>{{ __('players online') }}</span></span>
    </div>
    @endif
    <a class="strip-cell" role="listitem" href="{{ route('servers.index') }}">
        @include('theme::partials.voxel-emblem', ['emblem' => 'waystone', 'emblemClass' => 'strip-emblem'])
        <span class="strip-copy"><strong>{{ __('Servers') }}</strong><span>{{ trans_choice(':count server|:count servers', $stripServers->count(), ['count' => $stripServers->count()]) }}</span></span>
    </a>
    @if($site['modules']['store'])
    <a class="strip-cell" role="listitem" href="{{ route('store.index') }}">
        @include('theme::partials.voxel-emblem', ['emblem' => 'chest', 'emblemClass' => 'strip-emblem'])
        <span class="strip-copy"><strong>{{ __('Store') }}</strong><span>{{ __('Browse products') }}</span></span>
    </a>
    @endif
    @if(!empty($site['discord']))
    <a class="strip-cell strip-discord" role="listitem" href="{{ $site['discord'] }}" rel="noopener noreferrer">
        @include('theme::partials.voxel-emblem', ['emblem' => 'shield', 'emblemClass' => 'strip-emblem'])
        <span class="strip-copy"><strong>Discord</strong><span>{{ __('Join Discord') }}</span></span>
    </a>
    @elseif($site['modules']['tickets'] ?? false)
    <a class="strip-cell" role="listitem" href="{{ route('tickets.index') }}">
        @include('theme::partials.voxel-emblem', ['emblem' => 'shield', 'emblemClass' => 'strip-emblem'])
        <span class="strip-copy"><strong>{{ __('Support') }}</strong><span>{{ __('Open a ticket') }}</span></span>
    </a>
    @endif
</div>
