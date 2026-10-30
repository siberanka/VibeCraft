@extends('theme::layouts.app')
@section('title', __('Server handbook'))
@section('description', __('Find the rules, commands and guides for your next adventure.'))
@section('content')
<div class="wrap page knowledge">
    <header class="page-head knowledge-head">
        @include('theme::partials.voxel-emblem', ['emblem' => 'journal'])
        <div><h1>{{ __('Server handbook') }}</h1><p>{{ __('Find the rules, commands and guides for your next adventure.') }}</p></div>
    </header>
    <div class="knowledge-layout">
        <aside class="knowledge-sidebar">@include('theme::wiki.categories')</aside>
        <div class="knowledge-main">
            <form class="knowledge-search form" method="get" action="{{ route('wiki.index') }}" role="search">
                @if($selectedCategory)<input type="hidden" name="category" value="{{ $selectedCategory->slug }}">@endif
                <div class="field"><label for="wiki-search">{{ __('Search the handbook') }}</label><input type="search" id="wiki-search" name="q" value="{{ $search }}" maxlength="80"></div>
                <button type="submit" class="button">@include('theme::partials.icon', ['icon' => 'search']) {{ __('Search') }}</button>
            </form>
            @include('theme::partials.errors')
            @if($selectedCategory)<header class="knowledge-section"><h2>{{ $selectedCategory->name }}</h2>@if($selectedCategory->description)<p>{{ $selectedCategory->description }}</p>@endif</header>@endif
            @if($search !== '')<p class="knowledge-search-result">{{ __('Results for “:query”', ['query' => $search]) }} <a href="{{ route('wiki.index', $selectedCategory ? ['category' => $selectedCategory->slug] : []) }}">{{ __('Clear search') }}</a></p>@endif
            <div class="knowledge-list">
                @forelse($articles as $article)
                    <article class="knowledge-entry">
                        <div class="knowledge-entry-mark">@include('theme::partials.voxel-emblem', ['emblem' => 'journal'])</div>
                        <div><a class="knowledge-category" href="{{ route('wiki.index', ['category' => $article->category->slug]) }}">{{ $article->category->name }}</a><h2><a href="{{ route('wiki.show', $article->slug) }}">{{ $article->title }}</a></h2>@if($article->parent)<p class="knowledge-parent">{{ __('Parent page') }}: <a href="{{ route('wiki.show', $article->parent->slug) }}">{{ $article->parent->title }}</a></p>@endif @if($article->summary)<p>{{ $article->summary }}</p>@endif<time datetime="{{ $article->updated_at->toIso8601String() }}">{{ __('Updated :date', ['date' => $article->updated_at->translatedFormat('d F Y')]) }}</time></div>
                    </article>
                @empty
                    @include('theme::partials.empty', ['emptyTitle' => __('No articles found'), 'emptyText' => $search !== '' ? __('No articles match this search. Try another word or category.') : __('No guides have been published here yet.')])
                @endforelse
            </div>
            @include('theme::partials.pagination', ['paginator' => $articles])
        </div>
    </div>
</div>
@endsection
