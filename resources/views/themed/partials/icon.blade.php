@php
    $iconPaths = [
        'home' => ['M2 11h3V8h3V5h3V3h2v2h3v3h3v3h3', 'M5 11v10h5v-6h4v6h5V11'],
        'menu' => ['M4 6h16M4 12h16M4 18h16'],
        'exit' => ['M10 4H4v16h6', 'M10 12h11m-4-4 4 4-4 4'],
        'compass' => ['m12 2 10 10-10 10L2 12 12 2Z', 'm15 9-2 4-4 2 2-4 4-2Z'],
        'chest' => ['M3 9h18v12H3V9Z', 'M3 9V5h18v4M3 13h18M10 11h4v5h-4v-5Z'],
        'settings' => ['M4 6h16M4 12h16M4 18h16', 'M8 3v6m8 0v6m-8 0v6'],
        'receipt' => ['M5 3h14v18l-3-2-4 2-4-2-3 2V3Z', 'M9 7h6M9 11h6M9 15h3'],
        'pickaxe' => ['M4 5h9l6 6-3 3-5-5H4V5Z', 'm12 11-8 9 3 1 8-9'],
        'gem' => ['m3 8 5-5h8l5 5-9 13L3 8Z', 'M3 8h18M8 3l4 18 4-18'],
        'close' => ['m6 6 12 12M6 18 18 6'],
        'copy' => ['M9 9h11v11H9z', 'M15 5V3H3v12h2'],
        'search' => ['M5 3h8v2h2v8h-2v2H5v-2H3V5h2V3Z', 'm15 15 6 6'],
        'server' => ['m12 1 10 6-10 6L2 7 12 1Z', 'M2 7v10l10 6 10-6V7M12 13v10'],
        'store' => ['M3 4h18v17H3V4Z', 'M3 9h18M3 14h7m4 0h7M10 11h4v6h-4v-6Z'],
        'news' => ['M3 4h7l2 2 2-2h7v16h-7l-2 2-2-2H3V4Z', 'M12 6v16M6 9h3m6 0h3M6 13h3m6 0h3'],
        'support' => ['M5 3h14v2h3v12h-3v2H8l-5 3v-5H1V5h4V3Z', 'M6 9h12M6 13h8'],
        'account' => ['M8 2h8v10H8V2Z', 'M4 22v-7h4v-2h8v2h4v7'],
        'shield' => ['M3 3h18v11h-2v3h-3v3h-3v2h-2v-2H8v-3H5v-3H3V3Z', 'm8 11 3 3 5-6'],
        'arrow' => ['M4 12h16', 'm14 6 6 6-6 6'],
        'check' => ['m5 12 4 4L19 6'],
        'chevron' => ['m9 5 7 7-7 7'],
        'clock' => ['M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'M12 7v5l3 2'],
        'cart' => ['M2 3h3l3 13h11l3-9H6', 'M9 21h.01M18 21h.01'],
    ];
@endphp
<svg class="icon {{ $iconClass ?? '' }}" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true" focusable="false">@foreach($iconPaths[$icon ?? 'arrow'] ?? $iconPaths['arrow'] as $iconPath)<path d="{{ $iconPath }}"/>@endforeach</svg>
