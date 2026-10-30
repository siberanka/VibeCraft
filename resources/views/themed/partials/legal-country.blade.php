@if($legalRequiresMarketChoice ?? true)
<form method="get" action="{{ $legalFormAction }}" class="legal-country-form form">
    @foreach($legalQueryFields ?? [] as $queryName => $queryValue)<input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">@endforeach
    <div class="field"><label for="agreement-country">{{ __('Country or territory') }}</label><select id="agreement-country" name="country" required autocomplete="country"><option value="">{{ __('Choose a country') }}</option>@foreach($legalCountries as $code => $name)<option value="{{ $code }}" @selected($legalCountry === $code)>{{ $name }}</option>@endforeach</select></div>
    @if($legalProfiles->where('subdivision', '!=', '')->isNotEmpty())<div class="field"><label for="agreement-subdivision">{{ __('Subdivision') }}</label><select id="agreement-subdivision" name="subdivision"><option value="">{{ $legalProfiles->contains('subdivision', '') ? __('Country-wide profile') : __('Choose a subdivision') }}</option>@foreach($legalProfiles->where('subdivision', '!=', '') as $profile)<option value="{{ $profile->subdivision }}" @selected($legalSubdivision === $profile->subdivision)>{{ $profile->subdivision }}</option>@endforeach</select></div>@endif
    <button class="button button-quiet" type="submit">{{ __('Review documents') }}</button>
</form>
<p class="hint">{{ __('Choose your actual country. Your language choice does not change which country terms apply.') }}</p>
@if($legalCountry !== '' && !$legalSelection)<p class="alert" role="status">{{ __('Legal information for this market and language is not ready. Registration and new purchases remain unavailable for this selection.') }}</p>@endif
@elseif($legalSelection)
<p class="hint">{{ __('Published documents are provided automatically in your selected language.') }}</p>
@else
<p class="alert" role="status">{{ __('Published documents are not available for this language yet. Please contact the community team.') }}</p>
@endif
