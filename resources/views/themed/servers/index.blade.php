@extends('theme::layouts.app')
@section('title', __('Servers'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Servers') }}</h1></header>
<div class="server-board">@forelse($servers ?? $publicServers as $server)
<article class="world-card server-card" id="server-{{ $server->id }}">
    <div class="world-card-head">
        @include('theme::partials.voxel-emblem', ['emblem' => ['gateway', 'waystone', 'shield'][$loop->index % 3], 'emblemClass' => 'world-emblem'])
        <div><h2>{{ $server->name }}</h2>@if($server->description)<p>{{ $server->description }}</p>@endif</div>
    </div>
    @if(!$server->status_checked_at)
        <span class="status-label status-unknown">{{ __('Not checked') }}</span>
    @elseif($server->status_online)
        @php($fill = $server->status_max_players ? min(100, (int) round(($server->status_players / max(1, $server->status_max_players)) * 100)) : 0)
        <div class="world-load"><span class="status-label status-online">{{ $server->status_players }} / {{ $server->status_max_players }} {{ __('players online') }}</span><span class="world-meter" aria-hidden="true"><span class="world-meter-fill fill-{{ intdiv($fill, 10) }}"></span></span></div>
    @else
        <span class="status-label status-offline">{{ __('Offline') }}</span>
    @endif
    <div class="connection-actions server-join">@if($server->java_address)@include('theme::partials.copy-button', ['address' => $server->java_address, 'editionLabel' => 'Java'])@endif @if($server->bedrock_address)@include('theme::partials.copy-button', ['address' => $server->bedrock_address, 'editionLabel' => 'Bedrock', 'bedrockPort' => $server->bedrock_port])@endif</div>
    @if($server->bedrock_address)<p class="small muted">{{ __('For Bedrock, enter the address and port in separate fields.') }}</p>@endif
    @if($server->java_address || $server->bedrock_address)@include('theme::partials.connection-help')@endif
    @if($server->status_checked_at)<p class="status-freshness">@if($server->status_version){{ $server->status_version }} — @endif{{ __('Last checked') }}: <time datetime="{{ $server->status_checked_at->toIso8601String() }}">{{ $server->status_checked_at->diffForHumans() }}</time></p>@endif
</article>@empty @include('theme::partials.empty', ['emptyTitle' => __('No public servers yet'), 'emptyText' => __('Server connection details will appear here when they are available.')]) @endforelse</div>
</div>
@endsection
