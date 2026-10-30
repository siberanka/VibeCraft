@extends('theme::layouts.app')
@section('title', $member->name)
@section('content')
<div class="wrap page"><header class="page-head profile-head"><h1>{{ $member->name }}</h1>
<div class="profile-badges">@if($staffRole)<span class="badge-pill tone-gold">{{ __('role.'.$staffRole) }}</span>@endif @foreach($badges as $badge)<span class="badge-pill tone-{{ $badge->tone() }}" @if($badge->description) title="{{ $badge->description }}" @endif>{{ $badge->name }}</span>@endforeach</div></header>
<div class="leaderboard-grid section">
<section class="world-card"><h2>{{ __('Profile') }}</h2>
<p>{{ __('Joined') }}: {{ $member->created_at?->translatedFormat('d M Y') }}</p>
@if($identity)<p>Minecraft: <strong>{{ $identity->username }}</strong> <small class="muted">({{ ucfirst($identity->edition) }})</small></p>@endif
<p>{{ __('Forum posts') }}: {{ number_format($posts) }}</p></section>
@if($stats !== [])
<section class="world-card"><h2>{{ __('Leaderboards') }}</h2>
<ul class="leaderboard-list">@foreach(['playtime_minutes', 'kills', 'deaths', 'mob_kills'] as $stat)@if(isset($stats[$stat]))<li><span class="leaderboard-name">{{ __('stat.'.$stat) }}</span><span class="leaderboard-value">{{ $stat === 'playtime_minutes' ? __(':hours h', ['hours' => number_format(intdiv($stats[$stat], 60))]) : number_format($stats[$stat]) }}</span></li>@endif @endforeach</ul></section>
@endif
</div></div>
@endsection
