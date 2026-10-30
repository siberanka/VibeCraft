@extends('theme::layouts.app')
@section('title', __('Complete payment'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Complete payment') }}</h1><p>{{ __('Payment is processed by PayTR. Return to your order to check its status.') }}</p></header><iframe src="{{ $iframeUrl }}" title="{{ __('PayTR secure payment') }}" width="100%" height="680" referrerpolicy="no-referrer" allow="payment" class="payment-frame"></iframe><div class="form-actions"><a href="{{ route('orders.show', $order) }}">{{ __('Back to order') }}</a></div></div>
@endsection
