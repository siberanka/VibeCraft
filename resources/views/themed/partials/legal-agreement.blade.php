<fieldset class="legal-agreement"><legend>{{ __('Terms and notices') }}</legend>
    <input type="hidden" name="legal_country" value="{{ $legalSelection['market']->country }}">
    <input type="hidden" name="legal_subdivision" value="{{ $legalSelection['market']->subdivision }}">
    <input type="hidden" name="legal_locale" value="{{ app()->getLocale() }}">
    <input type="hidden" name="legal_review_token" value="{{ $legalReviewToken }}">
    <ul class="legal-notice-links">
        @foreach($legalSelection['documents'] as $kind => $document)
            <li><input type="hidden" name="legal_document_ids[{{ $kind }}]" value="{{ $document->id }}"><a href="{{ route('legal.documents.show', $document) }}" lang="{{ $document->locale }}">{{ $document->title }}</a><span class="hint">{{ $legalKinds[$kind] }} / {{ __('Published version :number', ['number' => $document->version]) }}</span></li>
        @endforeach
    </ul>
    <p class="hint">{{ __('Privacy and cookie notices explain data use. They are provided for information and are not a request for optional marketing or tracking consent.') }}</p>
    <label class="checkbox"><input type="checkbox" name="terms" value="1" required><span>{{ ($legalAgreementContext ?? 'registration') === 'registration' ? __('I accept the community terms linked above.') : __('I accept the community terms and terms of sale linked above.') }}</span></label>
</fieldset>
