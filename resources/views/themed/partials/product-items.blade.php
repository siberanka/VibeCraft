@forelse($products ?? [] as $product)
<article class="product-item">
    @if($product->image_media_id)<a href="{{ route('store.product', $product) }}" tabindex="-1" aria-hidden="true"><img @class(['product-image', 'media-icon' => app(\App\Domain\Media\MediaLibrary::class)->isLibrary($product->image_media_id)]) src="{{ route('media.show', $product->image_media_id) }}" alt="" width="600" height="450" loading="lazy"></a>@else<a class="product-emblem" href="{{ route('store.product', $product) }}" tabindex="-1" aria-hidden="true">@include('theme::partials.voxel-emblem', ['emblem' => 'chest'])</a>@endif
    <div class="product-body">
        @if($product->badge)<span class="product-badge">{{ $product->badge }}</span>@endif
        <h2><a href="{{ route('store.product', $product) }}">{{ $product->name }}</a></h2>
        @if($product->summary)<p>{{ $product->summary }}</p>@endif
        <div class="product-price">{{ \App\Support\Money::format($product->price_minor) }}@if($product->compare_at_minor > $product->price_minor)<del>{{ \App\Support\Money::format($product->compare_at_minor) }}</del>@endif</div>
        <div class="product-bottom">
            @if($product->inStock())<a class="button button-small" href="{{ route('store.product', $product) }}">{{ __('View product') }}</a>@else<span class="muted small">{{ __('Out of stock') }}</span>@endif
            @if($product->duration_days)<span class="muted small">{{ $product->duration_days }} {{ __('days') }}</span>@endif
        </div>
    </div>
</article>
@empty
    @include('theme::partials.empty', ['emptyTitle' => __('No products available'), 'emptyText' => __('Products will appear here when the store opens.')])
@endforelse
