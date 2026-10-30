@if(isset($paginator) && method_exists($paginator, 'hasPages') && $paginator->hasPages())
<nav class="pagination" aria-label="{{ __('Pagination') }}">
    @if($paginator->previousPageUrl())<a class="button button-quiet button-small" href="{{ $paginator->previousPageUrl() }}">{{ __('Previous') }}</a>@else<span></span>@endif
    <span class="muted small">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->nextPageUrl())<a class="button button-quiet button-small" href="{{ $paginator->nextPageUrl() }}">{{ __('Next') }}</a>@else<span></span>@endif
</nav>
@endif
