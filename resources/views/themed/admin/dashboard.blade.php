@extends('theme::layouts.app')
@section('title', __('Administration'))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __('Administration') }}</h1><p>{{ __('Manage your community and follow its operations.') }}</p></header>@include('theme::admin.nav')<div class="admin-shell">
    @if(!empty($stats))<dl class="stats-row">@foreach($stats as $label => $value)<div><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong><span>{{ __($label) }}</span></div>@endforeach</dl>@endif
    <section><div class="section-head"><h2>{{ __('Recent orders') }}</h2>@can('orders.view')<a href="{{ url('/admin/orders') }}">{{ __('All orders') }}</a>@endcan</div>@if(collect($recentOrders ?? [])->isEmpty())@include('theme::partials.empty', ['emptyTitle' => __('No recent orders'), 'emptyText' => __('New orders will appear here as customers place them.')])@else<div class="table-wrap" tabindex="0" role="region" aria-label="{{ __('Recent orders') }}"><table class="data-table"><thead><tr><th scope="col">{{ __('Order') }}</th><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Total') }}</th></tr></thead><tbody>@foreach($recentOrders as $order)<tr><td><code>{{ $order->public_id }}</code></td><td>{{ $order->user?->name }}</td><td>{{ __($order->status) }}</td><td>{{ \App\Support\Money::format($order->total_minor, $order->currency) }}</td></tr>@endforeach</tbody></table></div>@endif</section>
</div></div>
@endsection
