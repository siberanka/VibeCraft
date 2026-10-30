@extends('theme::layouts.app')
@section('title', __('Notifications'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Notifications') }}</h1></header>
<div class="forum-list section">@forelse($notices as $notice)
@php($noticeData = (array) $notice->data)
<a class="forum-row" href="{{ url($noticeData['path'] ?? '/') }}">@include('theme::partials.voxel-emblem', ['emblem' => 'lantern', 'emblemClass' => 'forum-emblem'])<span class="forum-row-text"><strong>{{ __('notice.'.($noticeData['kind'] ?? 'order_paid'), (array) ($noticeData['params'] ?? [])) }}</strong></span><span class="forum-count">@if($notice->read_at === null)<span class="news-chip">{{ __('New') }}</span><br>@endif<small>{{ $notice->created_at?->diffForHumans() }}</small></span></a>
@empty @include('theme::partials.empty', ['emptyTitle' => __('No notifications'), 'emptyText' => __('Updates about your orders, tickets and applications appear here.')]) @endforelse</div>
</div>
@endsection
