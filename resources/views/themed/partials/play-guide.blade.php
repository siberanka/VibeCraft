<section class="minecraft-guide">
    <h2>{{ __('Connection guide') }}</h2>
    <ol>
        <li><strong>{{ __('Open Minecraft') }}</strong><span>{{ __('Use the edition shown beside the server address.') }}</span></li>
        <li><strong>{{ __('Add a server') }}</strong><span>{{ __('Copy the address into your multiplayer server list.') }}</span></li>
        <li><strong>{{ __('Enter your world') }}</strong><span>{{ __('Choose the server and join the community in game.') }}</span></li>
    </ol>
    @if(!empty($site['bedrock_address']))<p class="small muted">{{ __('For Bedrock, enter the address and port in separate fields.') }}</p>@endif
    <a href="{{ route('servers.index') }}">{{ __('All connection details') }}</a>
</section>
