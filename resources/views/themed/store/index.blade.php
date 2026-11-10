@extends('theme::layouts.app')
@section('title', __('Community store'))
@section('content')
@php($category = $category ?? collect($categories ?? [])->firstWhere('slug', request('category')))
<div class="wrap page"><header class="page-head"><h1>{{ ($category ?? null)?->name ?? __('Community store') }}</h1>@if(($category ?? null)?->description)<p>{{ $category->description }}</p>@endif</header>
<div class="store-layout {{ in_array($themeId, ['forge', 'nomad'], true) ? 'catalog-rail' : '' }}">
<nav class="category-nav" aria-label="{{ __('Product categories') }}"><a href="{{ route('store.index') }}" @if(!($category ?? null)) aria-current="page" @endif>{{ __('All products') }}</a>@foreach($categories ?? [] as $catalogCategory)<a href="{{ route('store.index', ['category' => $catalogCategory->slug]) }}" @if(($category ?? null)?->id === $catalogCategory->id) aria-current="page" @endif>{{ $catalogCategory->name }}</a>@endforeach</nav>
<div><div class="{{ in_array($themeId, ['mono', 'terminal'], true) ? 'catalog-list' : 'product-grid' }}">@include('theme::partials.product-items')</div>@include('theme::partials.pagination', ['paginator' => $products ?? null])</div>
</div></div>
@endsection
