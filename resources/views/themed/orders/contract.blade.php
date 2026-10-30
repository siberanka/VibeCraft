@extends('theme::layouts.app')
@section('title', __('Your original order confirmation'))
@section('content')
<div class="wrap page legal-portal order-contract">
    <header class="page-head legal-head">@include('theme::partials.voxel-emblem', ['emblem' => 'journal'])<div><h1>{{ __('Your original order confirmation') }}</h1><p>{{ $order->public_id }}</p></div></header>
    <p>{{ __('This copy preserves the products, price, trader and terms recorded when you confirmed the order.') }}</p>
    <div class="form-actions"><a class="button" href="{{ route('orders.contract', [$order, 'download' => 1]) }}">{{ __('Download your copy') }}</a><a class="button button-quiet" href="{{ route('orders.show', $order) }}">{{ __('Order details') }}</a></div>
    <div class="legal-document-layout">
        @include('theme::legal.trader', ['traderSnapshot' => $snapshot['trader'], 'traderSubdivision' => $snapshot['subdivision'], 'documentLocale' => $snapshot['locale']])
        <div class="legal-copy"><section><h2>{{ $snapshot['review']['context'] === 'topup' ? __('Balance top-up') : __('Ordered products') }}</h2>
            @foreach($snapshot['review']['lines'] as $line)<div class="order-contract-line"><h3>{{ $line['name'] }}</h3><p>{{ __('Quantity') }}: {{ $line['quantity'] }} / {{ \App\Support\Money::format($line['total_minor'], $snapshot['review']['currency']) }}</p>@if($line['summary'])<p>{{ $line['summary'] }}</p>@endif</div>@endforeach
            @if(($snapshot['review']['bonus_minor'] ?? 0) > 0)<p>{{ __('Bonus credit') }}: +{{ \App\Support\Money::format($snapshot['review']['bonus_minor'], $snapshot['review']['currency']) }}</p>@endif
            <p><strong>{{ __('Total payable') }}: {{ \App\Support\Money::format($snapshot['review']['total_minor'], $snapshot['review']['currency']) }}</strong></p>
            <p><time datetime="{{ $contract->recorded_at->toIso8601String() }}">{{ $contract->recorded_at->translatedFormat('d F Y H:i') }}</time></p>
        </section>
        @foreach($snapshot['documents'] as $document)<details class="order-contract-document"><summary><span lang="{{ $document['locale'] }}">{{ $document['title'] }}</span> / {{ __('Published version :number', ['number' => $document['version']]) }}</summary>
            <p class="hint">{{ $document['action'] === 'accepted' ? __('Accepted') : __('Provided for information') }}</p><div class="prose knowledge-prose" lang="{{ $document['locale'] }}">{!! $rendered[$document['kind']]['html'] !!}</div>
        </details>@endforeach</div>
    </div>
</div>
@endsection
