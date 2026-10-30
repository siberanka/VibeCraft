@extends('theme::layouts.app')
@section('title', __('Media library'))
@section('content')
<div class="wrap page">
    <header class="page-head"><h1>{{ __('Media library') }}</h1><p>{{ __('Upload community artwork and choose it in your content and product editors.') }}</p></header>
    @include('theme::admin.nav')
    @include('theme::partials.errors')
    <div class="split">
        <section>
            <h2>{{ __('Your images') }}</h2>
            @error('media')<p class="alert" role="alert">{{ $message }}</p>@enderror
            <p class="muted">{{ __(':count ready-made icons are always available in every image field.', ['count' => $bundledCount]) }}</p>
            @if(count($records) === 0)
                @include('theme::partials.empty', ['emptyTitle' => __('No images yet'), 'emptyText' => __('Upload your first image using the form.')])
            @else
                <div class="table-wrap form-footer" role="region" aria-label="{{ __('Uploaded images') }}" tabindex="0">
                    <table class="data-table">
                        <thead><tr><th scope="col">{{ __('Preview') }}</th><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Image details') }}</th><th scope="col">{{ __('Use') }}</th></tr></thead>
                        <tbody>
                        @foreach($records as $media)
                            <tr>
                                <td><a class="media-preview" href="{{ $media->url() }}"><img src="{{ $media->url() }}" alt="{{ $media->alt ?: __('Uploaded image') }}" width="120" height="80" loading="lazy"></a></td>
                                <td><strong>{{ $media->alt ?: __('Untitled image') }}</strong><p class="small muted">{{ __('Image') }} #{{ $media->id }}</p></td>
                                <td class="small">{{ $media->width }} × {{ $media->height }}<br>{{ number_format($media->size / 1024) }} KB<br><time datetime="{{ $media->created_at->toIso8601String() }}">{{ $media->created_at->format('d.m.Y') }}</time></td>
                                <td>@if(($useCounts[$media->id] ?? 0) > 0)<span class="order-status">{{ trans_choice('Used in :count place|Used in :count places', $useCounts[$media->id], ['count' => $useCounts[$media->id]]) }}</span>@else<form method="post" action="{{ route('admin.media.destroy', $media) }}" data-confirm="{{ __('Delete this image?') }}">@csrf @method('DELETE')<button class="text-button small" type="submit">{{ __('Delete') }}</button></form>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @include('theme::partials.pagination', ['paginator' => $records])
            @endif
        </section>
        <aside class="panel">
            <h2>{{ __('Upload an image') }}</h2>
            <form action="{{ route('admin.media.store') }}" method="post" enctype="multipart/form-data" class="form" data-submit-once>
                @csrf
                <div class="field"><label for="image">{{ __('Image file') }}</label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="upload-hint"><p class="hint" id="upload-hint">{{ __('JPEG, PNG or WebP. Up to 4 MB and 4 million pixels.') }}</p></div>
                <div class="field"><label for="alt">{{ __('Image description') }}</label><input id="alt" name="alt" value="{{ old('alt') }}" maxlength="255" aria-describedby="alt-hint"><p class="hint" id="alt-hint">{{ __('Describe what the image shows for visitors using a screen reader.') }}</p></div>
                <button class="button" type="submit">{{ __('Upload image') }}</button>
            </form>
        </aside>
    </div>
</div>
@endsection
