@extends('theme::layouts.app')
@section('title', $product->name)
@section('description', $product->summary ?? '')
@section('content')
<div class="wrap page">@include('theme::partials.errors')
<div class="product-detail">
    @if($product->image_media_id)<img @class(['product-image', 'media-icon' => app(\App\Domain\Media\MediaLibrary::class)->isLibrary($product->image_media_id)]) src="{{ route('media.show', $product->image_media_id) }}" alt="{{ $product->name }}" width="720" height="540">@else<div class="product-showcase" aria-hidden="true">@include('theme::partials.voxel-emblem', ['emblem' => 'chest'])</div>@endif
    <section class="product-purchase"><h1>{{ $product->name }}</h1>@if($product->summary)<p class="muted">{{ $product->summary }}</p>@endif<div class="product-price">{{ \App\Support\Money::format($product->price_minor) }}@if($product->compare_at_minor > $product->price_minor)<del>{{ \App\Support\Money::format($product->compare_at_minor) }}</del>@endif</div>
    <div class="product-facts">@if($product->duration_days)<span>{{ __('Duration') }}: {{ $product->duration_days }} {{ __('days') }}</span>@endif @if($product->stock !== null && $product->stock <= 10)<span>{{ __('Only :count left', ['count' => $product->stock]) }}</span>@endif @if($product->requires_link)<span>{{ __('Verified Minecraft account required') }}</span>@endif</div>
    @if(!$product->inStock())<p class="alert">{{ __('This product is currently out of stock.') }}</p>
    @else @auth<form method="post" action="{{ route('cart.store') }}" class="purchase-form" data-submit-once>@csrf<input type="hidden" name="product_id" value="{{ $product->id }}"><div class="purchase-controls"><div class="field"><label for="quantity">{{ __('Quantity') }}</label><input id="quantity" name="quantity" type="number" min="1" max="{{ $product->max_quantity }}" value="1" required></div><button class="button" type="submit">{{ __('Add to cart') }}</button></div></form>@else<a href="{{ route('login') }}" class="button">{{ __('Log in to purchase') }}</a>@endauth @endif
    </section>
</div>@if($product->description_html)<section class="section"><h2>{{ __('Product details') }}</h2><div class="prose form-footer">{!! $product->description_html !!}</div></section>@endif</div>
@endsection
