@extends('theme::layouts.app')
@section('title', __('New topic'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('New topic') }}</h1><p>{{ $category->name }}</p></header>@include('theme::partials.errors')
<form method="post" action="{{ route('forum.store', $category) }}" class="form" data-submit-once>@csrf
<div class="field"><label for="topic-title">{{ __('Title') }}</label><input id="topic-title" name="title" maxlength="120" value="{{ old('title') }}" required></div>
<div class="field"><label for="topic-body">{{ __('Message') }}</label><textarea id="topic-body" name="body" rows="8" maxlength="10000" required>{{ old('body') }}</textarea></div>
@include('theme::partials.captcha', ['captchaForm' => 'forum'])<button class="button" type="submit">{{ __('Post topic') }}</button></form>
</div>
@endsection
