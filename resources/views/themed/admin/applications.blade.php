@extends('theme::layouts.app')
@section('title', __('Staff applications'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Staff applications') }}</h1></header>@include('theme::admin.nav')
<nav class="category-nav" aria-label="{{ __('Status') }}">@foreach(['pending', 'accepted', 'rejected'] as $tab)<a href="{{ route('admin.applications.index', ['status' => $tab]) }}" @if($status === $tab) aria-current="page" @endif>{{ __('application.'.$tab) }}</a>@endforeach</nav>
<div class="table-wrap section"><table class="data-table"><thead><tr><th>{{ __('Account') }}</th><th>{{ __('Position') }}</th><th>{{ __('Date') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead><tbody>
@forelse($applications as $application)<tr><td>{{ $application->user?->name }}</td><td>{{ $application->position?->name }}</td><td>{{ $application->created_at?->translatedFormat('d M Y H:i') }}</td><td><a href="{{ route('admin.applications.show', $application) }}">{{ __('Review') }}</a></td></tr>
@empty<tr><td colspan="4">{{ __('No applications here.') }}</td></tr>@endforelse
</tbody></table></div>
@include('theme::partials.pagination', ['paginator' => $applications])
</div>
@endsection
