@extends('theme::layouts.app')
@section('title', __('Forum'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Forum') }}</h1></header>
<div class="forum-list section">@forelse($categories as $forumCategory)
<a class="forum-row" href="{{ route('forum.category', $forumCategory) }}">@include('theme::partials.voxel-emblem', ['emblem' => 'journal', 'emblemClass' => 'forum-emblem'])<span class="forum-row-text"><strong>{{ $forumCategory->name }}</strong>@if($forumCategory->description)<span>{{ $forumCategory->description }}</span>@endif</span><span class="forum-count">{{ trans_choice(':count topic|:count topics', $forumCategory->threads_count, ['count' => $forumCategory->threads_count]) }}</span></a>
@empty @include('theme::partials.empty', ['emptyTitle' => __('The forum is not open yet'), 'emptyText' => __('Forum categories will appear here.')]) @endforelse</div>
</div>
@endsection
