<details class="join-guide" data-disclosure>
    <summary>@include('theme::partials.icon', ['icon' => 'pickaxe']) {{ __('Connection help') }} @include('theme::partials.icon', ['icon' => 'chevron', 'iconClass' => 'menu-chevron'])</summary>
    <div class="join-guide-content">
        @if($server->java_address)
            <p><strong>Java Edition</strong> {{ __('Open Multiplayer, add a server and paste the Java address shown above.') }}</p>
        @endif
        @if($server->bedrock_address)
            <p><strong>Bedrock Edition</strong> {{ __('On devices that support custom servers, add the Bedrock address and port in separate fields.') }}</p>
        @endif
        <p class="small muted">{{ __('If you cannot connect, check your edition and the server status before trying again.') }}</p>
    </div>
</details>
