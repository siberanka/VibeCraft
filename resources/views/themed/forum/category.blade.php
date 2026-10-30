@extends('theme::layouts.app')
@section('title', $category->name)
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $category->name }}</h1>@if($category->description)<p>{{ $category->description }}</p>@endif</header>
@if($canStart)<div class="form-actions"><a class="button" href="{{ route('forum.create', $category) }}">{{ __('New topic') }}</a></div>@endif
<div class="forum-list section">@forelse($threads as $forumThread)
<a class="forum-row" href="{{ route('forum.thread', $forumThread) }}"><span class="forum-row-text"><strong>@if($forumThread->pinned)<span class="news-chip">{{ __('Pinned') }}</span> @endif{{ $forumThread->title }}@if($forumThread->locked) <span class="muted">({{ __('Locked') }})</span>@endif</strong><span>{{ $forumThread->author?->name ?? __('Deleted account') }}</span></span><span class="forum-count">{{ trans_choice(':count reply|:count replies', $forumThread->replies, ['count' => $forumThread->replies]) }}<br><small>{{ $forumThread->last_post_at?->diffForHumans() }}</small></span></a>
@empty @include('theme::partials.empty', ['emptyTitle' => __('No topics yet'), 'emptyText' => __('Start the first conversation.')]) @endforelse</div>
@include('theme::partials.pagination', ['paginator' => $threads])
</div>
@endsection
