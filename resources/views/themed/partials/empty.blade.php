<div class="empty">
    @php($emptyEmblem = match (true) {
        request()->routeIs('store.*', 'cart.*', 'orders.*') => 'chest',
        request()->routeIs('tickets.*') => 'lantern',
        request()->routeIs('news.*') => 'journal',
        request()->routeIs('wiki.*', 'admin.wiki.*') => 'journal',
        request()->routeIs('account.*') => 'shield',
        request()->routeIs('servers.*') => 'waystone',
        default => 'gateway',
    })
    @include('theme::partials.voxel-emblem', ['emblem' => $emptyEmblem])
    <h3>{{ $emptyTitle ?? __('Nothing here yet') }}</h3>
    <p>{{ $emptyText ?? __('New content will appear here when it is published.') }}</p>
</div>
