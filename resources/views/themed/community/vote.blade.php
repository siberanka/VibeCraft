@extends('theme::layouts.app')
@section('title', __('Vote'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Vote for us') }}</h1></header>
<div class="vote-board">@forelse($sites as $voteSite)
<article class="world-card vote-card">
    <div class="world-card-head">@include('theme::partials.voxel-emblem', ['emblem' => 'shield', 'emblemClass' => 'world-emblem'])<div><h2>{{ $voteSite->name }}</h2><p>{{ trans_choice('Every :count hour|Every :count hours', $voteSite->cooldown_hours, ['count' => $voteSite->cooldown_hours]) }}</p></div></div>
    <a class="button" href="{{ $voteSite->url }}" rel="noopener noreferrer nofollow" target="_blank">{{ __('Vote') }}</a>
</article>
@empty @include('theme::partials.empty', ['emptyTitle' => __('No vote sites yet'), 'emptyText' => __('Vote links will appear here.')]) @endforelse</div>
</div>
@endsection
