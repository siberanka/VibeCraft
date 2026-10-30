@extends('theme::layouts.app')
@section('title', $post->title)
@section('description', $post->excerpt ?? '')
@section('content')
<article class="wrap page news-article"><header class="page-head"><h1>{{ $post->title }}</h1><div class="article-meta"><time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->translatedFormat('d F Y') }}</time>@if($post->category)<span class="news-chip">{{ $post->category }}</span>@endif @if($post->author)<span>{{ $post->author->name }}</span>@endif</div>@if($post->excerpt)<p>{{ $post->excerpt }}</p>@endif</header>@if($post->cover_media_id)<img class="article-cover" src="{{ route('media.show', $post->cover_media_id) }}" alt="" width="1280" height="720">@endif<div class="prose">{!! $post->body_html !!}</div><div class="form-actions"><a href="{{ route('news.index') }}">{{ __('Back to all news') }}</a></div></article>
@endsection
