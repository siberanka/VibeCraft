@extends('theme::layouts.app')
@section('title', __('Custom CSS'))
@section('content')
@php
    $cssSnippets = [
        __('Block-shadow headline') => ".stage-title {\n    text-shadow: 3px 3px 0 var(--c-line), 6px 6px 0 var(--c-bg);\n}",
        __('Hotbar menu') => ".site-navigation .nav-link[aria-current=\"page\"] {\n    border-radius: 0;\n    box-shadow: inset 0 0 0 3px var(--c-accent), 0 3px 0 var(--c-bg);\n}",
        __('Pixel-notched cards') => ".world-card,\n.product-item {\n    clip-path: polygon(0 6px, 6px 6px, 6px 0, calc(100% - 6px) 0, calc(100% - 6px) 6px, 100% 6px, 100% calc(100% - 6px), calc(100% - 6px) calc(100% - 6px), calc(100% - 6px) 100%, 6px 100%, 6px calc(100% - 6px), 0 calc(100% - 6px));\n}",
        __('Inventory-slot images') => ".product-image {\n    border: 3px solid var(--c-line);\n    border-radius: 0;\n    background: var(--c-bg);\n    padding: 6px;\n}",
    ];
@endphp
<div class="wrap page"><header class="page-head"><h1>{{ __('Custom CSS') }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
<div class="split">
<form method="post" action="{{ route('admin.custom-css.update') }}" class="form" data-submit-once>@csrf @method('PUT')
    <div class="field"><label for="custom-css">{{ __('CSS for the public site') }}</label>
    <textarea id="custom-css" name="css" rows="22" maxlength="{{ \App\Core\Theme\CustomCss::MAX_BYTES }}" spellcheck="false" autocomplete="off">{{ old('css', $source) }}</textarea>
    @error('css')<p class="field-error" role="alert">{{ $message }}</p>@enderror
    <p class="hint">{{ __('Applies to every theme, not to the admin panel. Images may only come from /media/ or /assets/.') }}</p></div>
    <div class="form-actions"><button class="button" type="submit">{{ __('Save CSS') }}</button></div>
</form>
<section class="flow" aria-labelledby="snippets-heading"><h2 id="snippets-heading">{{ __('Ready-made snippets') }}</h2>
@foreach($cssSnippets as $snippetName => $snippetCss)
<details class="panel"><summary>{{ $snippetName }}</summary><pre><code>{{ $snippetCss }}</code></pre></details>
@endforeach
</section>
</div>
</div>
@endsection
