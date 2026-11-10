@extends('theme::layouts.app')
@section('content')
<div class="wrap">
<section class="stage stage-brick" aria-labelledby="home-heading">
    @include('theme::partials.stage-copy')
    <div class="brick-frame">
        @include('theme::partials.world-scene', ['sceneSizes' => '(max-width:760px) calc(100vw - 40px), (max-width:1400px) 48vw, 640px'])
    </div>
</section>
</div>
<div class="wrap">
    @foreach($homeSections ?? \App\Core\Theme\HomeLayout::SECTIONS as $homeSection)
    @if($homeSection === 'strip')
    @include('theme::partials.stage-strip')
    @elseif($homeSection === 'worlds')
    <section class="section stage-section" aria-labelledby="worlds-heading">
        <div class="section-head"><h2 id="worlds-heading">{{ __('Servers') }}</h2><a href="{{ route('servers.index') }}">{{ __('View all') }}</a></div>
        @include('theme::partials.stage-worlds')
    </section>
    @elseif($homeSection === 'news' && $site['modules']['news'])
    <section class="section stage-section" aria-labelledby="news-heading">
        <div class="section-head"><h2 id="news-heading">{{ __('Latest news') }}</h2><a href="{{ route('news.index') }}">{{ __('View all') }}</a></div>
        @include('theme::partials.stage-news')
    </section>
    @elseif($homeSection === 'purchases')
    @include('theme::partials.recent-purchases')
    @elseif($homeSection === 'store' && $site['modules']['store'] && collect($products ?? [])->isNotEmpty())
    <section class="section stage-section" aria-labelledby="store-heading">
        <div class="section-head"><h2 id="store-heading">{{ __('Store') }}</h2><a href="{{ route('store.index') }}">{{ __('View all') }}</a></div>
        <div class="product-grid">@include('theme::partials.product-items')</div>
    </section>
    @endif
    @endforeach
</div>
@endsection
