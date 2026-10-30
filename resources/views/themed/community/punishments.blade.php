@extends('theme::layouts.app')
@section('title', __('Punishments'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Punishments') }}</h1></header>
<form method="get" action="{{ route('community.punishments') }}" class="form form-inline section" role="search"><div class="field"><label for="player-search">{{ __('Player') }}</label><input id="player-search" name="player" value="{{ $search }}" maxlength="16" autocomplete="off"></div><button class="button" type="submit">{{ __('Search') }}</button></form>
@if($punishments->isEmpty())
@include('theme::partials.empty', ['emptyTitle' => __('No active punishments'), 'emptyText' => __('Active bans shared by our servers appear here.')])
@else
<div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('Player') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Staff') }}</th><th>{{ __('Date') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Server') }}</th></tr></thead><tbody>
@foreach($punishments as $punishment)<tr><td>{{ $punishment->player_name }}</td><td>{{ $punishment->reason ?? '—' }}</td><td>{{ $punishment->staff ?? '—' }}</td><td>{{ $punishment->issued_at?->translatedFormat('d M Y') ?? '—' }}</td><td>{{ $punishment->expires_at?->translatedFormat('d M Y H:i') ?? __('Permanent') }}</td><td>{{ $punishment->server?->name }}</td></tr>@endforeach
</tbody></table></div>
@include('theme::partials.pagination', ['paginator' => $punishments])
@endif
</div>
@endsection
