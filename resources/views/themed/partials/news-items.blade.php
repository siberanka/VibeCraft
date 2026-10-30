@forelse($posts ?? [] as $post)
<article class="news-item">
    @if($post->cover_media_id)<a href="{{ route('news.show', $post) }}" tabindex="-1" aria-hidden="true"><img src="{{ route('media.show', $post->cover_media_id) }}" alt="" width="720" height="405" loading="lazy"></a>@else<div class="news-insignia">@include('theme::partials.voxel-emblem', ['emblem' => 'journal'])</div>@endif
    <div class="news-meta"><time datetime="{{ $post->published_at?->toIso8601String() }}">{{ $post->published_at?->translatedFormat('d M Y') }}</time>@if($post->category)<span>{{ $post->category }}</span>@endif</div>
    <div><h3><a href="{{ route('news.show', $post) }}">{{ $post->title }}</a></h3>@if($post->excerpt)<p>{{ $post->excerpt }}</p>@endif</div>
</article>
@empty
    @include('theme::partials.empty', ['emptyTitle' => __('News from the community'), 'emptyText' => __('Announcements and updates will be published here.')])
@endforelse
