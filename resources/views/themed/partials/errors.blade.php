@php($allErrorMessages = collect($errors->getBags())->flatMap(fn ($bag) => $bag->all())->unique()->values())
@if($allErrorMessages->isNotEmpty())
<div class="alert alert-error" role="alert" tabindex="-1">
    <strong>{{ __('Please check the following fields.') }}</strong>
    <ul>@foreach($allErrorMessages as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
