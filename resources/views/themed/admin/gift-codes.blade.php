@extends('theme::layouts.app')
@section('title', __('Credit codes'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Credit codes') }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
@if(session('generated_codes'))
<section class="alert" aria-labelledby="new-codes-heading">
    <h2 id="new-codes-heading">{{ __('Copy these codes now. They are shown only once.') }}</h2>
    <label class="sr-only" for="new-codes">{{ __('Credit codes') }}</label>
    <textarea id="new-codes" rows="{{ min(12, count(session('generated_codes'))) }}" readonly spellcheck="false">{{ implode("\n", session('generated_codes')) }}</textarea>
</section>
@endif
<div class="split">
<form method="post" action="{{ route('admin.gift-codes.store') }}" class="form" data-submit-once>@csrf
    <div class="field"><label for="count">{{ __('Number of codes') }}</label><input id="count" name="count" type="number" min="1" max="200" value="{{ old('count', 1) }}" required></div>
    <div class="field"><label for="amount">{{ __('Amount') }} ({{ \App\Support\Money::currency() }})</label><input id="amount" name="amount" inputmode="decimal" maxlength="15" value="{{ old('amount') }}" required></div>
    <div class="field"><label for="max_redemptions">{{ __('Uses per code') }}</label><input id="max_redemptions" name="max_redemptions" type="number" min="1" max="10000" value="{{ old('max_redemptions', 1) }}" required></div>
    <div class="field"><label for="expires_at">{{ __('Expires') }}</label><input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}"></div>
    <div class="field"><label for="note">{{ __('Note') }}</label><input id="note" name="note" maxlength="120" value="{{ old('note') }}"></div>
    <button class="button" type="submit">{{ __('Create codes') }}</button>
</form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>{{ __('Code') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Used') }}</th><th>{{ __('Expires') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody>
@forelse($codes as $code)
<tr><td>…{{ $code->code_hint }}@if($code->note)<br><small class="muted">{{ $code->note }}</small>@endif</td><td>{{ \App\Support\Money::format($code->amount_minor) }}</td><td>{{ $code->redemptions }} / {{ $code->max_redemptions }}</td><td>{{ $code->expires_at?->translatedFormat('d M Y H:i') ?? '—' }}</td><td>{{ $code->is_active ? __('Active') : __('Disabled') }}</td>
<td>@if($code->is_active)<form method="post" action="{{ route('admin.gift-codes.deactivate', $code) }}" data-submit-once>@csrf<button class="text-button" type="submit">{{ __('Disable') }}</button></form>@endif</td></tr>
@empty<tr><td colspan="6">{{ __('No credit codes yet.') }}</td></tr>@endforelse
</tbody></table></div></div>
@include('theme::partials.pagination', ['paginator' => $codes])
</div>
@endsection
