@extends('theme::layouts.app')
@section('title', __('Staff applications'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Staff applications') }}</h1></header>@include('theme::partials.errors')
<div class="vote-board">@forelse($positions as $position)
<article class="world-card">
    <div class="world-card-head">@include('theme::partials.voxel-emblem', ['emblem' => 'waystone', 'emblemClass' => 'world-emblem'])<div><h2>{{ $position->name }}</h2></div></div>
    @if($position->description)<p>{!! nl2br(e($position->description)) !!}</p>@endif
    @auth<a class="button" href="{{ route('community.apply.form', $position) }}">{{ __('Apply') }}</a>@else<a class="button" href="{{ route('login') }}">{{ __('Log in to apply') }}</a>@endauth
</article>
@empty @include('theme::partials.empty', ['emptyTitle' => __('No open positions'), 'emptyText' => __('Open staff positions will appear here.')]) @endforelse</div>
@if(collect($mine)->isNotEmpty())
<section class="section"><div class="section-head"><h2>{{ __('My applications') }}</h2></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('Position') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr></thead><tbody>
@foreach($mine as $application)<tr><td>{{ $application->position?->name }}</td><td>{{ __('application.'.$application->status) }}@if($application->review_note && $application->status !== 'pending')<br><small class="muted">{{ $application->review_note }}</small>@endif</td><td>{{ $application->created_at?->translatedFormat('d M Y') }}</td></tr>@endforeach
</tbody></table></div></section>
@endif
</div>
@endsection
