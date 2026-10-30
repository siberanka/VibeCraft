@php($activeRanks = \App\Models\Entitlement::query()->where('user_id', auth()->id())->where('status', 'active')->with('orderItem.product')->orderBy('expires_at')->get())
@if($activeRanks->isNotEmpty())
<section class="panel" id="ranks" aria-labelledby="ranks-heading"><h2 id="ranks-heading">{{ __('Active ranks') }}</h2>
@error('renewal')<p class="field-error" role="alert">{{ $message }}</p>@enderror
@foreach($activeRanks as $rank)
<div class="server-line"><div><strong>{{ $rank->orderItem?->product_name }}</strong><small>{{ __('Ends') }}: {{ $rank->expires_at?->translatedFormat('d M Y H:i') }}</small>
@if($rank->auto_renew)<small>{{ __('Renews for :price every :days days from your balance.', ['price' => \App\Support\Money::format((int) $rank->renew_price_minor), 'days' => $rank->orderItem?->duration_days * max(1, (int) $rank->orderItem?->quantity)]) }}</small>@endif</div>
@if($rank->orderItem?->product)
<form method="post" action="{{ route('account.renewal', $rank) }}" data-submit-once>@csrf<input type="hidden" name="auto_renew" value="{{ $rank->auto_renew ? 0 : 1 }}">
<button class="button button-small {{ $rank->auto_renew ? 'button-quiet' : '' }}" type="submit">{{ $rank->auto_renew ? __('Turn off renewal') : __('Renew for :price', ['price' => \App\Support\Money::format($rank->orderItem->product->price_minor * max(1, (int) $rank->orderItem->quantity))]) }}</button></form>
@endif
</div>
@endforeach
</section>
@endif
