@php($newsList = ($posts ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($posts->items()) : collect($posts ?? []))
@if($newsList->isEmpty())
    @include('theme::partials.empty', ['emptyTitle' => __('News from the community'), 'emptyText' => __('Announcements and updates will be published here.')])
@else
<div class="news-board">
    @foreach($newsList as $post)
    <article class="news-tile {{ $loop->first ? 'news-tile-lead' : '' }}">
        <a class="news-tile-media" href="{{ route('news.show', $post) }}" tabindex="-1" aria-hidden="true">
            @if($post->cover_media_id)<img src="{{ route('media.show', $post->cover_media_id) }}" alt="" width="960" height="540" loading="lazy">
            @else<span class="news-tile-art">@include('theme::partials.voxel-emblem', ['emblem' => 'journal'])</span>@endif
        </a>
        <div class="news-tile-body">
            <div class="news-tile-meta">@if($post->category)<span class="news-chip">{{ $post->category }}</span>@endif<time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->translatedFormat('d M Y') }}</time></div>
            <h3><a href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h3>
            @if($loop->first && $post->excerpt)<p>{{ $post->excerpt }}</p>@endif
        </div>
    </article>
    @endforeach
</div>
@endif
