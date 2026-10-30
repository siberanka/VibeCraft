@php($discordConfigured = (string) app(\App\Core\Settings\Settings::class)->get('discord.client_id') !== '' && app(\App\Core\Settings\Settings::class)->hasSecret('discord.client_secret'))
@if($discordConfigured)
@php($discordIdentity = \App\Models\SocialIdentity::query()->where('user_id', auth()->id())->where('provider', 'discord')->first())
<section class="panel" id="discord" aria-labelledby="discord-heading"><h2 id="discord-heading">Discord</h2>
@error('discord')<p class="field-error" role="alert">{{ $message }}</p>@enderror
@if($discordIdentity)<p>{{ __('Linked as :name', ['name' => $discordIdentity->username ?: $discordIdentity->provider_user_id]) }}</p>
<form method="post" action="{{ route('discord.unlink') }}" data-submit-once>@csrf @method('DELETE')<button class="button button-quiet" type="submit">{{ __('Unlink Discord') }}</button></form>
@else<a class="button" href="{{ route('discord.redirect') }}">{{ __('Link Discord') }}</a>@endif
</section>
@endif
