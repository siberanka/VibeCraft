<svg class="voxel-emblem {{ $emblemClass ?? '' }}" viewBox="0 0 64 64" width="64" height="64" aria-hidden="true" focusable="false">
    @switch($emblem ?? 'gateway')
        @case('chest')
            <path class="ve-shadow" d="m7 48 25 14 27-16-25-14Z"/>
            <path class="ve-top" d="M8 20 32 6 56 20 32 34Z"/>
            <path class="ve-front" d="m8 20 24 14v24L8 44Z"/>
            <path class="ve-side" d="m32 34 24-14v24L32 58Z"/>
            <path class="ve-band" d="m8 30 24 14 24-14v6L32 50 8 36Z"/>
            <path class="ve-trim" d="m28 33 8-5v14l-8 5Z"/>
            <path class="ve-edge" d="m8 20 24 14 24-14M32 34v24M18 14l24 14"/>
            @break
        @case('waystone')
            <path class="ve-shadow" d="m4 51 28 12 28-12-28-12Z"/>
            <path class="ve-top" d="m8 44 24-12 24 12-24 12Z"/>
            <path class="ve-front" d="m8 44 24 12v6L8 50Z"/>
            <path class="ve-side" d="m32 56 24-12v6L32 62Z"/>
            <path class="ve-top" d="M20 10 32 3 44 10 32 17Z"/>
            <path class="ve-front" d="m20 10 12 7v32l-12-7Z"/>
            <path class="ve-side" d="m32 17 12-7v32l-12 7Z"/>
            <path class="ve-trim" d="m25 23 4 2v13l-4-2Z"/>
            <path class="ve-edge" d="m20 10 12 7 12-7M32 17v32"/>
            @break
        @case('journal')
            <path class="ve-shadow" d="m7 48 20 14 32-19-20-14Z"/>
            <path class="ve-front" d="m8 23 19 11v25L8 48Z"/>
            <path class="ve-paper" d="m27 34 29-17v23L27 57Z"/>
            <path class="ve-top" d="M8 23 37 6 56 17 27 34Z"/>
            <path class="ve-side" d="m8 43 19 11 29-17v7L27 61 8 50Z"/>
            <path class="ve-trim" d="m27 16 8-5 19 11v17l-8 5V27Z"/>
            <path class="ve-edge" d="m13 26 19-11M27 34v20m5-12 20-12m-20 18 20-12"/>
            @break
        @case('lantern')
            <path class="ve-shadow" d="m7 50 25 13 25-13-25-13Z"/>
            <path class="ve-band" d="M23 4h18v5h-5V8h-9v8h-4Z"/>
            <path class="ve-top" d="m12 18 20-9 20 9-20 10Z"/>
            <path class="ve-front" d="m12 18 20 10v30L12 48Z"/>
            <path class="ve-side" d="m32 28 20-10v30L32 58Z"/>
            <path class="ve-trim" d="m18 28 9 5v15l-9-5Zm19 5 9-5v15l-9 5Z"/>
            <path class="ve-band" d="m12 44 20 10 20-10v6L32 60 12 50Z"/>
            <path class="ve-edge" d="m12 18 20 10 20-10M32 28v26"/>
            @break
        @case('shield')
            <path class="ve-shadow" d="m10 51 21 12 24-13-23-13Z"/>
            <path class="ve-side" d="m16 6 37 9v25l-8 11-15 10-15-16-4-15V10Z"/>
            <path class="ve-top" d="m11 10 5-4 37 9-6 5Z"/>
            <path class="ve-front" d="m11 10 36 10v21l-7 10-10 6-12-13-7-16Z"/>
            <path class="ve-band" d="m18 20 22 6v13l-4 7-6 4-8-9-4-15Z"/>
            <path class="ve-trim" d="m25 27 7 2v17l-7-7Z"/>
            @break
        @default
            <path class="ve-shadow" d="m5 51 27 12 27-13-27-13Z"/>
            <path class="ve-side" d="M17 5h36v44l-8 5V16H25v33l-8 5Z"/>
            <path class="ve-top" d="M9 10 17 5h36l-8 5Z"/>
            <path class="ve-front" d="M9 10h36v44h-9V20H18v34H9Z"/>
            <path class="ve-band" d="M9 27h9v5H9Zm27 0h9v5h-9ZM9 43h9v5H9Zm27 0h9v5h-9Z"/>
            <path class="ve-trim" d="M24 28h6v13h-6Z"/>
            <path class="ve-edge" d="M9 10h36v44M18 20h18"/>
    @endswitch
</svg>
