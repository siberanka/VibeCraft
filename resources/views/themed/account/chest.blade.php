@extends('theme::layouts.app')
@section('title', __('Chest'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Chest') }}</h1></header>@include('theme::partials.errors')
<div class="product-grid section">@forelse($items as $item)
<article class="product-item chest-item">
    <span class="product-emblem" aria-hidden="true">@include('theme::partials.voxel-emblem', ['emblem' => 'chest'])</span>
    <div class="product-body">
        <h2>{{ $item->product_name }}</h2>
        <p>@if($item->quantity > 1){{ $item->quantity }} × @endif{{ $item->player_name }}@if($item->duration_days) · {{ $item->duration_days }} {{ __('days') }}@endif</p>
        <form method="post" action="{{ route('chest.claim', $item->id) }}" data-submit-once>@csrf<button class="button" type="submit">{{ __('Claim') }}</button></form>
    </div>
</article>
@empty @include('theme::partials.empty', ['emptyTitle' => __('Your chest is empty'), 'emptyText' => __('Items you buy for later appear here.')]) @endforelse</div>
</div>
@endsection
