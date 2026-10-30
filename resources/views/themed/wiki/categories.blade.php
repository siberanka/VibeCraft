<nav class="knowledge-categories" aria-label="{{ __('Wiki categories') }}">
    <a href="{{ route('wiki.index') }}" @if(!isset($selectedCategory)) aria-current="page" @endif>{{ __('All articles') }}</a>
    @foreach($categories as $category)
        <a href="{{ route('wiki.index', ['category' => $category->slug]) }}" @if(($selectedCategory?->id ?? null) === $category->id) aria-current="page" @endif><span>{{ $category->name }}</span><span class="knowledge-count">{{ $category->articles_count }}</span></a>
    @endforeach
</nav>
