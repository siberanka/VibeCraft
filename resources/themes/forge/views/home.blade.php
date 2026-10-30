@extends('theme::layouts.app')
@section('content')
<div class="wrap">
    <section class="stage stage-forge" aria-labelledby="home-heading">
        <div class="forge-frame">@include('theme::partials.world-scene', ['sceneClass' => 'stage-media', 'sceneSizes' => '(max-width:1320px) calc(100vw - 40px), 1280px'])</div>
        @include('theme::partials.stage-copy', ['copyClass' => 'forge-plate'])
    </section>
    @foreach($homeSections ?? \App\Core\Theme\HomeLayout::SECTIONS as $homeSection)
    @if($homeSection === 'strip')
    @include('theme::partials.stage-strip')
    @elseif(in_array($homeSection, ['news', 'worlds'], true) && ! ($forgeColumns ?? false))
    @php($forgeColumns = true)
    @php($forgeNews = $site['modules']['news'] && in_array('news', $homeSections ?? [], true))
    @php($forgeWorlds = in_array('worlds', $homeSections ?? [], true))
    <div class="forge-columns @if(! ($forgeNews && $forgeWorlds)) forge-columns-single @endif">
        @if($forgeNews)
        <section class="section stage-section" aria-labelledby="news-heading">
        <div class="section-head"><h2 id="news-heading">{{ __('Latest news') }}</h2><a href="{{ route('news.index') }}">{{ __('View all') }}</a></div>
        @include('theme::partials.stage-news')
    </section>
        @endif
        @if($forgeWorlds)
        <aside class="section stage-section forge-rail" aria-labelledby="worlds-heading">
            <div class="section-head"><h2 id="worlds-heading">{{ __('Servers') }}</h2><a href="{{ route('servers.index') }}">{{ __('View all') }}</a></div>
            @include('theme::partials.stage-worlds', ['worldLimit' => 4])
        </aside>
        @endif
    </div>
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
