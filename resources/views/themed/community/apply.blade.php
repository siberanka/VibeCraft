@extends('theme::layouts.app')
@section('title', $position->name)
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $position->name }}</h1></header>@include('theme::partials.errors')
<form method="post" action="{{ route('community.apply', $position) }}" class="form" data-submit-once>@csrf
@foreach($questions as $index => $question)
    <div class="field"><label for="answer-{{ $index }}">{{ $question }}</label><textarea id="answer-{{ $index }}" name="answers[{{ $index }}]" rows="4" maxlength="2000" required>{{ old('answers.'.$index) }}</textarea></div>
@endforeach
@include('theme::partials.captcha', ['captchaForm' => 'application'])
<button class="button" type="submit">{{ __('Send application') }}</button>
</form>
</div>
@endsection
