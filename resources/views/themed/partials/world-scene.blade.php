@php
    // The choice made for this theme wins; the site-wide home image is the fallback before the bundled picture.
    $sceneArt = ($sceneOperatorImage ?? true) ? ($themeArt ?? ['mode' => 'default', 'media' => null]) : ['mode' => 'default', 'media' => null];
    $sceneOwn = $sceneArt['mode'] === 'own' ? route('media.show', ['media' => $sceneArt['media']]) : ((($sceneOperatorImage ?? true) && $sceneArt['mode'] === 'default') ? ($site['hero'] ?? null) : null);
@endphp
@if($sceneArt['mode'] === 'none')
@elseif(!empty($sceneOwn))
<figure class="world-scene operator-scene {{ $sceneClass ?? '' }}">
    <img src="{{ $sceneOwn }}" alt="" width="1600" height="900" fetchpriority="high" decoding="async">
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
        'prism' => 'Pixel-art aurora over a still lake with tall ice shards.',
        'arcade' => 'A bright pixel-art valley with a stone keep, a river and block clouds.',
        'terminal' => 'A moonlit keep above the water, drawn in four shades of green.',
        'brick' => 'A pile of colourful toy blocks with a yellow sun and white clouds.',
        'dusk' => 'A pixel-art sunset with a glowing gateway and a grid running to the horizon.',
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
