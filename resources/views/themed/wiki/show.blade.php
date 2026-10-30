@extends('theme::layouts.app')
@section('title', $article->title)
@section('description', $article->summary ?? '')
@section('content')
<div class="wrap page knowledge">
    <nav class="breadcrumbs" aria-label="{{ __('Breadcrumb') }}"><span><a href="{{ route('wiki.index') }}">{{ __('Server handbook') }}</a></span><span><a href="{{ route('wiki.index', ['category' => $article->category->slug]) }}">{{ $article->category->name }}</a></span>@foreach($ancestors as $ancestor)<span><a href="{{ route('wiki.show', $ancestor->slug) }}">{{ $ancestor->title }}</a></span>@endforeach</nav>
    <div class="knowledge-layout">
        <aside class="knowledge-sidebar">@include('theme::wiki.categories')</aside>
        <article class="knowledge-main">
            <header class="page-head"><h1>{{ $article->title }}</h1>@if($article->summary)<p>{{ $article->summary }}</p>@endif<time class="knowledge-updated" datetime="{{ $article->updated_at->toIso8601String() }}">{{ __('Updated :date', ['date' => $article->updated_at->translatedFormat('d F Y')]) }}</time></header>
            @if($article->table_of_contents)
                <details class="knowledge-toc" open><summary>{{ __('On this page') }}</summary><nav aria-label="{{ __('On this page') }}"><ol>@foreach($article->table_of_contents as $heading)<li class="knowledge-depth-{{ $heading['level'] }}"><a href="#{{ $heading['id'] }}">{{ $heading['label'] }}</a></li>@endforeach</ol></nav></details>
            @endif
            <div class="prose knowledge-prose">{!! $article->body_html !!}</div>
            @include('theme::wiki.faq', ['faqLocale' => $article->locale])
            <section class="knowledge-feedback" aria-labelledby="feedback-title"><h2 id="feedback-title">{{ __('Was this guide helpful?') }}</h2>
                @include('theme::partials.errors')
                @if(auth()->user()?->hasVerifiedEmail())
                    @if($feedbackVote !== null)<p class="hint">{{ __('Your feedback has been saved. You can change your choice for this revision.') }}</p>@endif
                    <form method="post" action="{{ route('wiki.feedback', $article->slug) }}" class="form-actions">@csrf<input type="hidden" name="revision" value="{{ $article->revision }}">
                        <button class="button button-quiet" type="submit" name="helpful" value="1" aria-pressed="{{ $feedbackVote === true ? 'true' : 'false' }}">{{ __('Yes, helpful') }}</button>
                        <button class="button button-quiet" type="submit" name="helpful" value="0" aria-pressed="{{ $feedbackVote === false ? 'true' : 'false' }}">{{ __('Not yet') }}</button>
                    </form>
                @elseif(auth()->check())<a href="{{ route('verification.notice') }}">{{ __('Verify your email to leave feedback.') }}</a>
                @else<a href="{{ route('login') }}">{{ __('Sign in to leave feedback.') }}</a>@endif
            </section>
            @if($childArticles->isNotEmpty())<section class="knowledge-subpages"><h2>{{ __('Subpages') }}</h2><ul>@foreach($childArticles as $child)<li><a href="{{ route('wiki.show', $child->slug) }}">{{ $child->title }}</a>@if($child->summary)<p>{{ $child->summary }}</p>@endif</li>@endforeach</ul></section>@endif
            @if($relatedArticles->isNotEmpty())<footer class="knowledge-related"><h2>{{ __('Continue exploring') }}</h2><ul>@foreach($relatedArticles as $related)<li><a href="{{ route('wiki.show', $related->slug) }}">{{ $related->title }}</a></li>@endforeach</ul></footer>@endif
            <div class="form-actions"><a class="button button-quiet" href="{{ route('wiki.index', ['category' => $article->category->slug]) }}">{{ __('Back to category') }}</a></div>
        </article>
    </div>
</div>
@endsection
