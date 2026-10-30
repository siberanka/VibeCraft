<dialog class="portal-dialog" data-confirm-dialog aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="dialog-heading">@include('theme::partials.icon', ['icon' => 'shield'])<h2 id="confirm-title">{{ __('Review this action') }}</h2></div>
    <p id="confirm-message" data-confirm-message></p>
    <div class="form-actions">
        <button class="button button-quiet" type="button" data-confirm-cancel>{{ __('Go back') }}</button>
        <button class="button button-danger" type="button" data-confirm-accept>{{ __('Confirm action') }}</button>
    </div>
</dialog>
<p class="sr-only" data-action-status role="status" aria-live="polite" data-busy-label="{{ __('Processing your request.') }}"></p>
