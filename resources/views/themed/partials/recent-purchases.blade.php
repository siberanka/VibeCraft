@if(collect($recentPurchases ?? [])->isNotEmpty())
<section class="section stage-section" aria-labelledby="recent-heading">
    <div class="section-head"><h2 id="recent-heading">{{ __('Recent purchases') }}</h2></div>
    <ul class="recent-purchases">
        @foreach($recentPurchases as $purchase)
        <li><strong>{{ $purchase['player'] }}</strong><span>{{ $purchase['product'] }}</span><time datetime="{{ $purchase['at']->toIso8601String() }}">{{ $purchase['at']->diffForHumans() }}</time></li>
        @endforeach
    </ul>
</section>
@endif
