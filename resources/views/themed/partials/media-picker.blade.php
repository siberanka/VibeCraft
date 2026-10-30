@php($picker = app(\App\Domain\Media\MediaLibrary::class)->picker())
@php($pickerValue = (string) ($pickerValue ?? ''))
@php($pickerCurrent = $pickerValue !== '' ? \App\Models\Media::query()->find((int) $pickerValue) : null)
@php($pickerView = $pickerCurrent && $pickerCurrent->disk === 'library' ? $pickerCurrent->library_pack.'-'.$pickerCurrent->library_category : 'uploads')
@php($pickerKey = preg_replace('/[^a-z0-9-]/', '-', strtolower($pickerId)))
<fieldset class="field media-picker" @if($pickerHint ?? null) aria-describedby="{{ $pickerId }}-hint" @endif>
    <legend>{{ $pickerLabel }}</legend>
    <div class="media-picker-current">
        @if($pickerCurrent)<img src="{{ $pickerCurrent->url() }}" alt="" width="64" height="64" @class(['media-icon' => $pickerCurrent->disk === 'library'])><span>{{ __($pickerCurrent->alt ?: __('Image').' #'.$pickerCurrent->id) }}</span>
        @else<span class="muted">{{ __('No image selected') }}</span>@endif
    </div>
    <details class="media-picker-panel" data-disclosure @if($errors->has($pickerError ?? $pickerName)) open @endif>
        <summary class="button button-quiet button-small">{{ __('Choose image') }}</summary>
        <div class="media-picker-body">
            {{-- View switches are detached from the form (form attribute) so they are never submitted. --}}
            <div class="media-picker-nav" role="radiogroup" aria-label="{{ __('Image sets') }}">
                <label class="media-option-none"><input type="radio" name="{{ $pickerName }}" value="" @checked($pickerValue === '')><span>{{ __('No image') }}</span></label>
                <div class="media-nav-item"><input class="media-tab sr-only" type="radio" form="{{ $pickerKey }}-views" name="{{ $pickerKey }}-view" id="{{ $pickerKey }}-uploads" @checked($pickerView === 'uploads')><label for="{{ $pickerKey }}-uploads">{{ __('My uploads') }} <span class="muted">{{ $picker['uploads']->count() }}</span></label></div>
                @foreach($picker['packs'] as $pickerPack)
                    <p class="media-pack-name">{{ __($pickerPack['name']) }}</p>
                    @foreach($pickerPack['categories'] as $pickerCategory)
                        <div class="media-nav-item"><input class="media-tab sr-only" type="radio" form="{{ $pickerKey }}-views" name="{{ $pickerKey }}-view" id="{{ $pickerKey }}-{{ $pickerCategory['id'] }}" @checked($pickerView === $pickerCategory['id'])><label for="{{ $pickerKey }}-{{ $pickerCategory['id'] }}">{{ __($pickerCategory['name']) }} <span class="muted">{{ $pickerCategory['icons']->count() }}</span></label></div>
                    @endforeach
                @endforeach
            </div>
            <div class="media-picker-panels">
                <div class="media-panel">
                    @if($picker['uploads']->isEmpty())<p class="muted">{{ __('No images yet') }}</p>@else
                    <div class="media-grid">@foreach($picker['uploads'] as $pickerMedia)<label class="media-option" title="{{ $pickerMedia->alt ?: __('Image').' #'.$pickerMedia->id }}"><input type="radio" name="{{ $pickerName }}" value="{{ $pickerMedia->id }}" @checked($pickerValue === (string) $pickerMedia->id)><img src="{{ $pickerMedia->url() }}" alt="" width="96" height="96" loading="lazy"><span class="sr-only">{{ $pickerMedia->alt ?: __('Image').' #'.$pickerMedia->id }}</span></label>@endforeach</div>@endif
                    @can('content.manage')<p><a href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">{{ __('Upload a new image') }}</a></p>@endcan
                </div>
                @foreach($picker['packs'] as $pickerPack)
                    @foreach($pickerPack['categories'] as $pickerCategory)
                        <div class="media-panel"><div class="media-grid">@foreach($pickerCategory['icons'] as $pickerMedia)<label class="media-option" title="{{ __($pickerMedia->alt) }}"><input type="radio" name="{{ $pickerName }}" value="{{ $pickerMedia->id }}" @checked($pickerValue === (string) $pickerMedia->id)><img class="media-icon" src="{{ $pickerMedia->url() }}" alt="" width="96" height="96" loading="lazy"><span class="sr-only">{{ __($pickerMedia->alt) }}</span></label>@endforeach</div></div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </details>
    @if($pickerHint ?? null)<p class="hint" id="{{ $pickerId }}-hint">{{ $pickerHint }}</p>@endif
    @error($pickerError ?? $pickerName)<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
