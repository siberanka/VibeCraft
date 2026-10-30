@if($faqs)
<section class="knowledge-faq" @if($faqSchema) itemscope itemtype="https://schema.org/FAQPage" @endif lang="{{ $faqLocale ?? app()->getLocale() }}">
    <h2>{{ __('Frequently asked questions') }}</h2>
    @foreach($faqs as $faq)
        <details @if($faqSchema) itemprop="mainEntity" itemscope itemtype="https://schema.org/Question" @endif>
            <summary @if($faqSchema) itemprop="name" @endif>{{ $faq['question'] }}</summary>
            <div @if($faqSchema) itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer" @endif><p class="knowledge-faq-answer" @if($faqSchema) itemprop="text" @endif>{{ $faq['answer'] }}</p></div>
        </details>
    @endforeach
</section>
@endif
