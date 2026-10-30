@extends('theme::layouts.app')
@section('title', __('Revision :number', ['number' => $revision->revision]))
@section('content')
<div class="wrap page knowledge">
    <header class="page-head"><h1>{{ __('Revision :number', ['number' => $revision->revision]) }}</h1><p lang="{{ $revision->locale }}">{{ $revision->title }}</p></header>
    @include('theme::admin.nav')
    <div class="form-actions wiki-history-actions">
        @if($revision->revision !== $article->revision)<a class="button" href="{{ route('admin.wiki.articles.edit', [$article, 'from_revision' => $revision->revision]) }}">{{ __('Review this revision') }}</a>@endif
        <a class="button button-quiet" href="{{ route('admin.wiki.articles.history', $article) }}">{{ __('Back to history') }}</a>
        <a class="button button-quiet" href="{{ route('admin.wiki.articles.edit', $article) }}">{{ __('Edit current article') }}</a>
    </div>
    <dl class="wiki-revision-meta"><div><dt>{{ __('Saved by') }}</dt><dd>{{ $revision->editor?->name ?? __('Unknown editor') }}</dd></div><div><dt>{{ __('Saved at') }}</dt><dd><time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->translatedFormat('d F Y H:i') }}</time></dd></div><div><dt>{{ __('Language') }}</dt><dd>{{ $revision->locale }}</dd></div><div><dt>{{ __('Current revision') }}</dt><dd>{{ $article->revision }}</dd></div></dl>
    @if($revision->origin === 'baseline')<p class="muted">{{ __('This baseline records the content available when history began. Earlier edits are unavailable.') }}</p>@elseif($revision->based_on_revision)<p class="muted">{{ __('Based on revision :number', ['number' => $revision->based_on_revision]) }}</p>@endif
    <section class="wiki-revision-preview" aria-label="{{ __('Revision preview') }}">
        <h2>{{ __('Saved content') }}</h2>@if($revision->summary)<p lang="{{ $revision->locale }}">{{ $revision->summary }}</p>@endif
        <div class="prose knowledge-prose" lang="{{ $revision->locale }}">{!! $previewHtml !!}</div>
        @include('theme::wiki.faq', ['faqs' => \App\Domain\Content\WikiFaq::entries($revision->faqs), 'faqSchema' => false, 'faqLocale' => $revision->locale])
    </section>
</div>
@endsection
