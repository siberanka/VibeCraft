@extends('theme::layouts.app')
@section('title', $page->title)
@if($page->meta_description) @section('description', $page->meta_description) @endif
@section('content')
<article class="wrap page"><nav class="breadcrumbs" aria-label="{{ __('Breadcrumb') }}"><span><a href="{{ route('home') }}">{{ __('Home') }}</a></span><span>{{ $page->title }}</span></nav><header class="page-head"><h1>{{ $page->title }}</h1></header><div class="prose">{!! $page->body_html !!}</div></article>
@endsection
