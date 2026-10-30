@extends('theme::layouts.app')
@section('title', __('Test payment'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Test payment') }}</h1><p>{{ __('This is a local test. No real payment will be taken.') }}</p></header>@include('theme::partials.errors')<section class="panel"><h2>{{ \App\Support\Money::format($order->total_minor, $order->currency) }}</h2><form method="post" action="{{ route('checkout.sandbox.confirm', ['reference' => $payment->provider_reference]) }}" class="form" data-submit-once>@csrf<p>{{ __('Confirm this test transaction to exercise the delivery workflow.') }}</p><div class="form-actions"><button class="button" type="submit">{{ __('Confirm test payment') }}</button><a class="button button-quiet" href="{{ route('orders.show', $order) }}">{{ __('Cancel') }}</a></div></form></section></div>
@endsection
