@extends('theme::layouts.app')
@section('title', __($title))
@section('content')
<div class="wrap page"><header class="page-head"><h1>{{ __($title) }}</h1></header>@include('theme::admin.nav')@include('theme::partials.errors')
@if($saveNotice ?? false)<p class="alert" role="note">{{ $saveNotice }}</p>@endif
@if(isset($restoreSourceRevision))<div class="alert wiki-restore-notice" role="note">{{ __('Reviewing revision :number. Saving creates a new revision. Publication starts as a draft; choose Published to publish immediately.', ['number' => $restoreSourceRevision]) }} {{ __('The current slug, category, language, parent page and reader access are retained.') }}</div>@endif
<div class="split"><form method="post" action="{{ $action }}" class="form" enctype="multipart/form-data" data-submit-once>@csrf @if(strtoupper($method ?? 'POST') !== 'POST') @method($method) @endif
@if(isset($contentRevision))<input type="hidden" name="revision" value="{{ old('revision', $contentRevision) }}">@endif
@if(isset($restoreSourceRevision))<input type="hidden" name="from_revision" value="{{ old('from_revision', $restoreSourceRevision) }}">@endif
@foreach($fields as $fieldName => $field)
    @php
        $storageName = $fieldName;
        $isCouponForm = isset($fields['min_subtotal_minor'], $fields['code']);
        $moneyNames = ['price_minor' => 'price', 'min_subtotal_minor' => 'min_subtotal'];
        $isMoney = isset($moneyNames[$storageName]) || ($isCouponForm && $storageName === 'value');
        if ($isMoney) {
            $fieldName = $moneyNames[$storageName] ?? 'discount';
            $field['type'] = 'text';
            $field['value'] = isset($field['value']) ? \App\Support\Money::toDecimalString((int) $field['value']) : '';
            $field['label'] = match ($storageName) {
                'price_minor' => 'Price',
                'min_subtotal_minor' => 'Minimum subtotal',
                default => 'Discount (amount or percent)',
            };
        }
    @endphp
    @php($fieldType = $field['type'] ?? 'text')
    @php($fieldValue = old($fieldName, array_key_exists('value', $field) ? $field['value'] : data_get($record ?? null, $fieldName)))
    @if($field['disabled'] ?? false) @php($fieldValue = $field['value'] ?? null) @endif
    @if($fieldType === 'checkbox')
        <input type="hidden" name="{{ $fieldName }}" value="0"><label class="checkbox"><input type="checkbox" name="{{ $fieldName }}" value="1" @checked($fieldValue)><span>{{ __($field['label'] ?? $fieldName) }}</span></label>
    @elseif($fieldType === 'faqs')
        @php($faqRows = array_values(array_slice(is_array($fieldValue) ? $fieldValue : [], 0, 10)))
        @if(count($faqRows) < 10) @php($faqRows[] = ['question' => '', 'answer' => '']) @endif
        <details class="field wiki-faq-editor" @if($errors->has('faqs') || $errors->has('faqs.*')) open @endif><summary>{{ __($field['label']) }}</summary>
            <p class="hint">{{ __('Add up to ten plain-text questions. Save to add another row; clear both fields to remove a question.') }}</p>
            @foreach($faqRows as $faqIndex => $faqRow)
                <fieldset class="wiki-faq-fields"><legend>{{ __('Question :number', ['number' => $faqIndex + 1]) }}</legend>
                    <div class="field"><label for="faq-question-{{ $faqIndex }}">{{ __('Question') }}</label><input id="faq-question-{{ $faqIndex }}" name="faqs[{{ $faqIndex }}][question]" maxlength="160" value="{{ is_string($faqRow['question'] ?? null) ? $faqRow['question'] : '' }}">@error('faqs.'.$faqIndex.'.question')<p class="field-error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="faq-answer-{{ $faqIndex }}">{{ __('Answer') }}</label><textarea id="faq-answer-{{ $faqIndex }}" name="faqs[{{ $faqIndex }}][answer]" maxlength="2000" rows="4">{{ is_string($faqRow['answer'] ?? null) ? $faqRow['answer'] : '' }}</textarea>@error('faqs.'.$faqIndex.'.answer')<p class="field-error">{{ $message }}</p>@enderror</div>
                </fieldset>
            @endforeach
        </details>
    @elseif($fieldType === 'media')
        @include('theme::partials.media-picker', ['pickerName' => $fieldName, 'pickerId' => 'field-'.$fieldName, 'pickerLabel' => __($field['label'] ?? $fieldName), 'pickerValue' => $fieldValue])
    @elseif($fieldType === 'checkboxes')
        @php($fieldValue = session()->hasOldInput() ? old($fieldName, []) : $fieldValue)
        <fieldset class="field checkbox-group" aria-describedby="hint-{{ $fieldName }}">
            <legend>{{ __($field['label'] ?? $fieldName) }}</legend>
            @foreach($field['options'] ?? [] as $optionValue => $optionLabel)
                <label class="checkbox" for="field-{{ $fieldName }}-{{ $loop->index }}"><input id="field-{{ $fieldName }}-{{ $loop->index }}" type="checkbox" name="{{ $fieldName }}[]" value="{{ $optionValue }}" @checked(is_array($fieldValue) && in_array((string) $optionValue, $fieldValue, true))><span>{{ __($optionLabel) }}</span></label>
            @endforeach
            <p class="hint" id="hint-{{ $fieldName }}">{{ __($field['hint'] ?? '') }}</p>
            @error($storageName)<p class="field-error">{{ $message }}</p>@enderror
            @error($storageName.'.*')<p class="field-error">{{ $message }}</p>@enderror
        </fieldset>
    @else
        <div class="field"><label for="field-{{ $fieldName }}">{{ __($field['label'] ?? $fieldName) }}</label>
        @if($fieldType === 'textarea')<textarea id="field-{{ $fieldName }}" name="{{ $fieldName }}" rows="{{ $field['rows'] ?? 8 }}" @required($field['required'] ?? false) @if(isset($field['max'])) maxlength="{{ $field['max'] }}" @endif>{{ $fieldValue }}</textarea>
        @elseif($fieldType === 'select')<select id="field-{{ $fieldName }}" name="{{ $fieldName }}" @required($field['required'] ?? false) @disabled($field['disabled'] ?? false)>@foreach($field['options'] ?? [] as $optionValue => $optionLabel)<option value="{{ $optionValue }}" @selected((string) $fieldValue === (string) $optionValue)>{{ __($isCouponForm && $storageName === 'type' ? ($optionValue === 'percent' ? 'Percentage discount' : 'Fixed amount') : $optionLabel) }}</option>@endforeach</select>
        @else<input id="field-{{ $fieldName }}" name="{{ $fieldName }}" type="{{ $fieldType }}" value="{{ in_array($fieldType, ['password', 'file'], true) ? '' : ($fieldType === 'datetime-local' && $fieldValue instanceof \DateTimeInterface ? $fieldValue->format('Y-m-d\TH:i') : $fieldValue) }}" @required($field['required'] ?? false) @if($isMoney) inputmode="decimal" maxlength="15" @endif @if(isset($field['min'])) min="{{ $field['min'] }}" @endif @if(isset($field['max'])) {{ $fieldType === 'number' ? 'max' : 'maxlength' }}="{{ $field['max'] }}" @endif @if($fieldType === 'password') autocomplete="new-password" @endif @if(isset($field['step'])) step="{{ $field['step'] }}" @endif>
        @endif
        @if(!empty($field['hint']))<p class="hint">{{ __($field['hint']) }}</p>@endif
        @if($isMoney)<p class="hint">{{ $storageName === 'value' ? __('For a percentage, enter 10 for 10%. Otherwise enter the discount amount.') : __('Enter an amount in the store currency.') }}</p>@endif
        @error($storageName)<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @endif
@endforeach
<div class="form-actions"><button class="button" type="submit">{{ __('Save changes') }}</button><a class="button button-quiet" href="{{ url('/admin/'.($resource ?? '')) }}">{{ __('Cancel') }}</a>@if($historyUrl ?? false)<a class="button button-quiet" href="{{ $historyUrl }}">{{ __('Revision history') }}</a>@endif @foreach($extraActions ?? [] as $extraAction)<a class="button button-quiet" href="{{ $extraAction['url'] }}">{{ $extraAction['label'] }}</a>@endforeach</div></form>
<aside><h2>{{ __('Before saving') }}</h2><p class="muted form-footer">{{ __('Check the values carefully. Changes are recorded in the audit log.') }}</p></aside></div>
@if(isset($record) && $record instanceof \App\Models\GameServer)<section class="section"><h2>{{ __('Game bridge credentials') }}</h2><p class="muted form-footer">{{ __('Rotate the key when connecting a new game server or replacing compromised credentials.') }}</p><form method="post" action="{{ url('/admin/servers/'.$record->id.'/rotate-key') }}" class="form-actions" data-confirm="{{ __('Replace this server key? The old key will stop working immediately.') }}">@csrf<button class="button button-quiet" type="submit">{{ __('Rotate bridge key') }}</button></form></section>@endif
</div>
@endsection
