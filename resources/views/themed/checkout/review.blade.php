@extends('theme::layouts.app')
@section('title', $legalAgreementContext === 'topup' ? __('Review balance top-up') : __('Review your order'))
@section('content')
<div class="wrap page legal-portal checkout-review">
    <header class="page-head legal-head">@include('theme::partials.voxel-emblem', ['emblem' => 'chest'])<div><h1>{{ $legalAgreementContext === 'topup' ? __('Review balance top-up') : __('Review your order') }}</h1><p>{{ __('Check the products, payment total and published terms before confirming.') }}</p></div></header>
    @include('theme::partials.errors')
    <div class="form-actions"><a class="button button-quiet" href="{{ $returnUrl }}">{{ __('Edit your selection') }}</a></div>
    <div class="checkout-review-layout">
        <section class="checkout-review-facts"><h2>{{ $legalAgreementContext === 'topup' ? __('Balance top-up') : __('Ordered products') }}</h2>
            @if($details['lines'])<ol class="checkout-review-lines">
                @foreach($details['lines'] as $line)<li><div class="checkout-review-line-heading"><h3>{{ $line['name'] }}</h3><strong>{{ \App\Support\Money::format($line['total_minor'], $details['currency']) }}</strong></div>
                    <p>{{ __('Quantity') }}: {{ $line['quantity'] }} / {{ __('Unit price') }}: {{ \App\Support\Money::format($line['unit_price_minor'], $details['currency']) }}</p>
                    @if($line['summary'])<p>{{ $line['summary'] }}</p>@endif
                    @if($line['duration_days'])<p>{{ __('Duration per unit: :days days', ['days' => $line['duration_days']]) }}</p>@endif
                    @if($renderedDescriptions[$line['product_id']] ?? null)<details class="checkout-product-details" open><summary>{{ __('Product details and delivery conditions') }}</summary><div class="prose knowledge-prose">{!! $renderedDescriptions[$line['product_id']]['html'] !!}</div></details>@endif
                </li>@endforeach
            </ol>@else<p>{{ __('The amount will be added to your account balance after confirmed payment.') }}</p>@endif
            @if(isset($details['gift_recipient']))<section class="checkout-target checkout-gift"><h2>{{ __('Gift for') }}</h2><p><strong>{{ $details['gift_recipient']['name'] }}</strong></p></section>@endif
            @if($details['minecraft_target'])<section class="checkout-target"><h2>{{ __('Minecraft delivery account') }}</h2><p>{{ $details['minecraft_target']['username'] }} / {{ ucfirst($details['minecraft_target']['edition']) }}</p></section>@endif
            <dl class="definition-list checkout-review-prices"><div><dt>{{ __('Subtotal') }}</dt><dd>{{ \App\Support\Money::format($details['subtotal_minor'], $details['currency']) }}</dd></div>
                @if($details['discount_minor'] > 0)<div><dt>{{ __('Discount') }}@if($details['coupon']) / {{ $details['coupon']['code'] }}@endif</dt><dd>{{ \App\Support\Money::format($details['discount_minor'], $details['currency']) }}</dd></div>@endif
                @if(($details['bonus_minor'] ?? 0) > 0)<div><dt>{{ __('Bonus credit') }}</dt><dd>+{{ \App\Support\Money::format($details['bonus_minor'], $details['currency']) }}</dd></div><div><dt>{{ __('Added to your balance') }}</dt><dd>{{ \App\Support\Money::format($details['total_minor'] + $details['bonus_minor'], $details['currency']) }}</dd></div>@endif
                <div><dt>{{ __('Payment method') }}</dt><dd>{{ $methodLabel }}</dd></div><div><dt>{{ __('Payment currency') }}</dt><dd>{{ $details['currency'] }}</dd></div>
                <div class="checkout-review-total"><dt>{{ __('Total payable') }}</dt><dd>{{ \App\Support\Money::format($details['total_minor'], $details['currency']) }}</dd></div>
            </dl>
            <p class="hint">{{ __('The amount shown is the amount this checkout charges. Review the linked pre-contract information for the trader’s tax and delivery conditions.') }}</p>
        </section>
        <section class="checkout-review-agreement"><h2>{{ $legalRequiresMarketChoice ? __('Country and published terms') : __('Published terms') }}</h2>
            @include('theme::partials.legal-country')
            @if($legalSelection)
                @include('theme::legal.trader', ['traderSnapshot' => $legalSelection['documents']['terms']->trader_snapshot, 'traderSubdivision' => $legalSelection['market']->subdivision, 'documentLocale' => app()->getLocale(), 'countryName' => $legalCountries[$legalCountry] ?? $legalCountry])
                <form method="post" action="{{ $confirmAction }}" class="form checkout-confirm" data-submit-once>@csrf
                    @foreach($postFields as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <input type="hidden" name="expected_total_minor" value="{{ $review->total() }}"><input type="hidden" name="expected_review_sha256" value="{{ $review->sha256 }}">
                    @include('theme::partials.legal-agreement')
                    <p>{{ __('Confirming creates an order with an obligation to pay the total shown above.') }}</p>
                    <p class="checkout-confirm-total"><span>{{ __('Total payable') }}</span><strong>{{ \App\Support\Money::format($details['total_minor'], $details['currency']) }}</strong></p>
                    <button class="button" type="submit">{{ __('Order and pay') }}</button>
                </form>
            @else<p>{{ $legalRequiresMarketChoice ? __('Select your country to see the applicable documents before confirming.') : __('Published documents are not available for this language yet. Please contact the community team.') }}</p>@endif
        </section>
    </div>
</div>
@endsection
