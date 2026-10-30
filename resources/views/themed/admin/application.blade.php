@extends('theme::layouts.app')
@section('title', __('Staff applications'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ $application->position?->name }} — {{ $application->user?->name }}</h1></header>@include('theme::admin.nav')
<div class="split"><div class="stack">
@foreach($application->answers as $item)<section class="panel"><h2>{{ $item['question'] ?? '' }}</h2><p>{!! nl2br(e($item['answer'] ?? '')) !!}</p></section>@endforeach
</div><aside class="stack"><section class="panel"><h2>{{ __('Status') }}</h2><p>{{ __('application.'.$application->status) }}</p>
@foreach($application->user?->identities ?? [] as $identity)<p class="small muted">{{ ucfirst($identity->edition) }}: {{ $identity->username }}</p>@endforeach
@if($application->status === 'pending')
<form method="post" action="{{ route('admin.applications.decide', $application) }}" class="form" data-submit-once>@csrf
<div class="field"><label for="note">{{ __('Note to the applicant') }}</label><textarea id="note" name="note" rows="3" maxlength="500"></textarea></div>
<div class="form-actions"><button class="button" name="decision" value="accepted" type="submit">{{ __('Accept') }}</button><button class="button button-quiet" name="decision" value="rejected" type="submit">{{ __('Reject') }}</button></div>
</form>@elseif($application->review_note)<p>{{ $application->review_note }}</p>@endif
</section></aside></div>
</div>
@endsection
