@php($captcha = app(\App\Domain\Captcha\CaptchaVerifier::class))
@if($captcha->enabledFor($captchaForm ?? 'register'))
    @php($captchaProvider = $captcha->provider())
    @php($captchaKey = app(\App\Core\Settings\Settings::class)->get('captcha.site_key'))
    @if($captchaProvider === 'turnstile')
        <div class="cf-turnstile" data-sitekey="{{ $captchaKey }}" data-action="{{ $captchaForm ?? 'register' }}" data-theme="auto"></div>
        @once @push('scripts')<script nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}" src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>@endpush @endonce
    @elseif($captchaProvider === 'hcaptcha')
        <div class="h-captcha" data-sitekey="{{ $captchaKey }}"></div>
        @once @push('scripts')<script nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}" src="https://js.hcaptcha.com/1/api.js" async defer></script>@endpush @endonce
    @elseif($captchaProvider === 'recaptcha')
        <div class="g-recaptcha" data-sitekey="{{ $captchaKey }}"></div>
        @once @push('scripts')<script nonce="{{ app(\App\Core\Security\CspNonce::class)->get() }}" src="https://www.google.com/recaptcha/api.js" async defer></script>@endpush @endonce
    @endif
    <noscript><p class="field-error">{{ __('Enable JavaScript to complete the security check.') }}</p></noscript>
@endif
