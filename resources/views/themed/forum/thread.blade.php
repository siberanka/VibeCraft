@extends('theme::layouts.app')
@section('title', $thread->title)
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $thread->title }}</h1><p><a href="{{ route('forum.category', $category) }}">{{ $category->name }}</a>@if($thread->locked) — {{ __('Locked') }}@endif</p></header>@include('theme::partials.errors')
@if($moderator)<form method="post" action="{{ route('forum.moderate', $thread) }}" class="form-actions" data-submit-once>@csrf
<button class="button button-quiet button-small" name="action" value="{{ $thread->pinned ? 'unpin' : 'pin' }}">{{ $thread->pinned ? __('Unpin') : __('Pin') }}</button>
<button class="button button-quiet button-small" name="action" value="{{ $thread->locked ? 'unlock' : 'lock' }}">{{ $thread->locked ? __('Unlock') : __('Lock') }}</button>
<button class="button button-quiet button-small" name="action" value="{{ $thread->hidden ? 'show' : 'hide' }}">{{ $thread->hidden ? __('Show topic') : __('Hide topic') }}</button></form>@endif
<div class="stack section">@foreach($posts as $forumPost)
<article class="panel forum-post @if($forumPost->hidden) is-hidden @endif" id="post-{{ $forumPost->id }}">
    <header class="forum-post-head"><strong>@if($forumPost->author)<a href="{{ route('profile.show', $forumPost->author->name) }}">{{ $forumPost->author->name }}</a>@else{{ __('Deleted account') }}@endif</strong><time datetime="{{ $forumPost->created_at?->toIso8601String() }}">{{ $forumPost->created_at?->diffForHumans() }}</time></header>
    <div class="prose">{!! $forumPost->body_html !!}</div>
    @if($moderator)<form method="post" action="{{ route('forum.moderate', $thread) }}" data-submit-once>@csrf<input type="hidden" name="post" value="{{ $forumPost->id }}"><button class="text-button" name="action" value="{{ $forumPost->hidden ? 'show_post' : 'hide_post' }}">{{ $forumPost->hidden ? __('Show reply') : __('Hide reply') }}</button></form>@endif
</article>
@endforeach</div>
@include('theme::partials.pagination', ['paginator' => $posts])
@auth @if(!$thread->locked || $moderator)
<form method="post" action="{{ route('forum.reply', $thread) }}" class="form section" data-submit-once>@csrf
<div class="field"><label for="reply-body">{{ __('Your reply') }}</label><textarea id="reply-body" name="body" rows="5" maxlength="10000" required>{{ old('body') }}</textarea></div>
@include('theme::partials.captcha', ['captchaForm' => 'forum'])<button class="button" type="submit">{{ __('Reply') }}</button></form>
@endif @else<p class="section"><a href="{{ route('login') }}">{{ __('Log in to reply') }}</a></p>@endauth
</div>
@endsection
