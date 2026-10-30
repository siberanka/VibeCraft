<aside class="legal-trader"><h2>{{ __('Trader information') }}</h2><dl>
    @foreach(['name' => 'Trader name', 'address' => 'Trader address', 'email' => 'Trader email', 'phone' => 'Trader phone', 'registration' => 'Business registration (if applicable)', 'tax_id' => 'Tax registration (if applicable)'] as $key => $label)
        @if(!empty($traderSnapshot[$key]))<div><dt>{{ __($label) }}</dt><dd>{{ $traderSnapshot[$key] }}</dd></div>@endif
    @endforeach
    <div><dt>{{ __('Country or territory') }}</dt><dd>{{ $countryName }}@if($traderSubdivision) / {{ $traderSubdivision }}@endif</dd></div>
    <div><dt>{{ __('Document language') }}</dt><dd>{{ $documentLocale === 'tr' ? 'Türkçe' : 'English' }}</dd></div>
</dl></aside>
