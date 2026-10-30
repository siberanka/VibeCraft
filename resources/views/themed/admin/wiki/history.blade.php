@extends('theme::layouts.app')
@section('title', __('Revision history'))
@section('content')
<div class="wrap page knowledge">
    <header class="page-head"><h1>{{ __('Revision history') }}</h1><p lang="{{ $article->locale }}">{{ $article->title }}</p></header>
    @include('theme::admin.nav')
    @include('theme::partials.errors')
    <div class="flow flow-between wiki-history-actions"><p>{{ __('Current revision :number', ['number' => $article->revision]) }}</p><a class="button button-quiet" href="{{ route('admin.wiki.articles.edit', $article) }}">{{ __('Edit current article') }}</a></div>
    <p class="muted">{{ __('Feedback for this revision: :yes helpful, :no not yet.', ['yes' => $feedbackSummary[1] ?? 0, 'no' => $feedbackSummary[0] ?? 0]) }}</p>
    @if($revisions->isEmpty())
        @include('theme::partials.empty', ['emptyTitle' => __('No revisions yet'), 'emptyText' => __('The current content will be recorded when the next edit is saved.')])
    @else
        <ol class="wiki-history-list">
            @foreach($revisions as $revision)
                <li class="wiki-history-row"><div>{{ __('Revision :number', ['number' => $revision->revision]) }}@if($revision->revision === $article->revision)<span class="wiki-revision-caption">{{ __('Current') }}</span>@endif</div><div><h2><a lang="{{ $revision->locale }}" href="{{ route('admin.wiki.articles.revision', [$article, $revision]) }}">{{ $revision->title }}</a></h2>@if($revision->origin === 'baseline')<span class="wiki-revision-caption">{{ __('Imported current content') }}</span>@elseif($revision->based_on_revision)<span class="wiki-revision-caption">{{ __('Based on revision :number', ['number' => $revision->based_on_revision]) }}</span>@endif</div><div><span class="wiki-revision-caption">{{ __('Saved by') }}: {{ $revision->editor?->name ?? __('Unknown editor') }}</span><time class="wiki-revision-caption" datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->translatedFormat('d M Y H:i') }}</time></div></li>
            @endforeach
        </ol>
        @include('theme::partials.pagination', ['paginator' => $revisions])
    @endif
</div>
@endsection
