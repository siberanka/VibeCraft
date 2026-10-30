@extends('theme::layouts.app')
@section('title', __('Leaderboards'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Leaderboards') }}</h1></header>
@if($servers->count() > 1)<nav class="category-nav" aria-label="{{ __('Servers') }}">@foreach($servers as $choice)<a href="{{ route('community.leaderboards', ['server' => $choice->slug]) }}" @if($server?->id === $choice->id) aria-current="page" @endif>{{ $choice->name }}</a>@endforeach</nav>@endif
@if($server)
<div class="leaderboard-grid section">
@foreach($boards as $stat => $rows)
<section class="world-card leaderboard" aria-labelledby="board-{{ $stat }}">
    <h2 id="board-{{ $stat }}">{{ __('stat.'.$stat) }}</h2>
    @if($rows === [])<p class="muted">{{ __('No scores yet.') }}</p>@else
    <ol class="leaderboard-list">@foreach($rows as $row)<li><span class="leaderboard-name">{{ $row['name'] }}</span><span class="leaderboard-value">{{ $stat === 'playtime_minutes' ? __(':hours h', ['hours' => number_format(intdiv($row['value'], 60))]) : number_format($row['value']) }}</span></li>@endforeach</ol>
    @endif
</section>
@endforeach
</div>
@else
@include('theme::partials.empty', ['emptyTitle' => __('No scores yet.'), 'emptyText' => __('Leaderboards appear once a game server shares its statistics.')])
@endif
</div>
@endsection
