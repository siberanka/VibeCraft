@php($measuredServers = collect($publicServers ?? [])->filter(fn ($server) => $server->status_checked_at !== null))
<span class="status-label">
    @if($measuredServers->isNotEmpty()){{ number_format((int) ($onlineTotal ?? 0)) }} {{ __('players online') }}@else{{ __('Status not checked yet') }}@endif
</span>
