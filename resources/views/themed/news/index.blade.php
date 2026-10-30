@extends('theme::layouts.app')
@section('title', __('Community news'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Community news') }}</h1></header><div class="section stage-section">@include('theme::partials.stage-news')</div>@include('theme::partials.pagination', ['paginator' => $posts ?? null])</div>
@endsection
