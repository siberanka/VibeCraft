<div class="connection-actions">
    @include('theme::partials.copy-button', ['address' => $site['java_address'] ?? '', 'editionLabel' => 'Java'])
    @if(!empty($site['bedrock_address']))
        @include('theme::partials.copy-button', ['address' => $site['bedrock_address'], 'editionLabel' => 'Bedrock', 'bedrockPort' => $site['bedrock_port'] ?? 19132])
    @endif
</div>
