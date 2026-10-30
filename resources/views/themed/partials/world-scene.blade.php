@if(!empty($site['hero']) && ($sceneOperatorImage ?? true))
<figure class="world-scene operator-scene {{ $sceneClass ?? '' }}">
    <img src="{{ $site['hero'] }}" alt="" width="1600" height="900" fetchpriority="high" decoding="async">
</figure>
@else
@php
    $sceneTheme = $sceneTheme ?? $themeId ?? 'atlas';
    $sceneDescriptions = [
        'atlas' => 'A voxel citadel surrounded by teal water and mountain peaks.',
        'forge' => 'A warm voxel village with workshops and terraced gardens.',
        'volt' => 'An angular voxel arena with orange light and dark stone.',
        'nomad' => 'Monumental sandstone ruins above a vast desert canyon.',
        'mono' => 'Snow-covered voxel islands in a quiet monochrome landscape.',
    ];
    $sceneFile = 'assets/images/'.$sceneTheme.'-landscape.webp';
@endphp
@if(isset($sceneDescriptions[$sceneTheme]) && is_file(public_path($sceneFile)))
<figure class="world-scene {{ $sceneClass ?? '' }}">
    <img src="{{ asset($sceneFile) }}"
         srcset="{{ asset('assets/images/'.$sceneTheme.'-landscape-768.webp') }} 768w, {{ asset($sceneFile) }} 1600w"
         sizes="{{ $sceneSizes ?? match ($sceneTheme) { 'atlas' => '(max-width:760px) calc(100vw - 40px), (max-width:1400px) 44vw, 560px', 'volt' => '(max-width:760px) calc(100vw - 40px), (max-width:1400px) 40vw, 500px', 'nomad' => '(max-width:760px) calc(100vw - 40px), (max-width:1400px) 65vw, 850px', default => '(max-width:1320px) calc(100vw - 40px), 1280px' } }}"
         alt="{{ __($sceneDescriptions[$sceneTheme]) }}" width="1600" height="901" fetchpriority="high" decoding="async">
</figure>
@endif
@endif
