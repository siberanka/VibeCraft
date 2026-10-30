<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Settings;
use App\Models\SocialIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Links a Discord account to the signed-in website account (OAuth 2.0 authorization
 * code flow with state and PKCE, scope "identify"). It never signs anyone in and
 * never merges accounts by e-mail.
 */
class DiscordLinkController extends Controller
{
    private const AUTHORIZE = 'https://discord.com/oauth2/authorize';

    private const TOKEN = 'https://discord.com/api/oauth2/token';

    private const ME = 'https://discord.com/api/users/@me';

    public function __construct(private Settings $settings) {}

    private function configured(): bool
    {
        return (string) $this->settings->get('discord.client_id') !== '' && $this->settings->hasSecret('discord.client_secret');
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->configured(), 404);
        $state = Str::random(40);
        $verifier = Str::random(64);
        $request->session()->put('discord_link', ['state' => $state, 'verifier' => $verifier, 'at' => time()]);

        return redirect()->away(self::AUTHORIZE.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => (string) $this->settings->get('discord.client_id'),
            'scope' => 'identify',
            'redirect_uri' => route('discord.callback'),
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]));
    }

    public function callback(Request $request, AuditLogger $audit): RedirectResponse
    {
        abort_unless($this->configured(), 404);
        $pending = $request->session()->get('discord_link');
        $back = redirect()->to(route('account.show').'#discord');
        if (! is_array($pending) || ! is_string($request->query('state')) || ! hash_equals((string) ($pending['state'] ?? ''), $request->query('state'))) {
            // A request without the matching state must not cancel the user's pending link.
            return $back->withErrors(['discord' => __('The Discord link could not be confirmed. Please try again.')]);
        }
        $request->session()->forget('discord_link');
        if (time() - (int) ($pending['at'] ?? 0) > 600 || ! is_string($request->query('code')) || strlen($request->query('code')) > 200) {
            return $back->withErrors(['discord' => __('The Discord link could not be confirmed. Please try again.')]);
        }

        try {
            $token = Http::asForm()->timeout(10)->post(self::TOKEN, [
                'client_id' => (string) $this->settings->get('discord.client_id'),
                'client_secret' => (string) $this->settings->secret('discord.client_secret'),
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'redirect_uri' => route('discord.callback'),
                'code_verifier' => (string) $pending['verifier'],
            ])->throw()->json('access_token');
            $me = is_string($token) ? Http::withToken($token)->timeout(10)->get(self::ME)->throw()->json() : null;
        } catch (Throwable $e) {
            Log::notice('discord.link_failed', ['error' => mb_substr($e->getMessage(), 0, 200)]);

            return $back->withErrors(['discord' => __('The Discord link could not be confirmed. Please try again.')]);
        }

        $id = is_array($me) ? (string) ($me['id'] ?? '') : '';
        if (! preg_match('/^\d{5,25}$/', $id)) {
            return $back->withErrors(['discord' => __('The Discord link could not be confirmed. Please try again.')]);
        }
        $name = mb_substr((string) ($me['global_name'] ?? $me['username'] ?? ''), 0, 64);

        $taken = SocialIdentity::query()->where('provider', 'discord')->where('provider_user_id', $id)->first();
        if ($taken !== null && $taken->user_id !== $request->user()->id) {
            return $back->withErrors(['discord' => __('This Discord account is already linked to another account.')]);
        }

        try {
            SocialIdentity::query()->updateOrCreate(['user_id' => $request->user()->id, 'provider' => 'discord'], ['provider_user_id' => $id, 'username' => $name]);
        } catch (UniqueConstraintViolationException) {
            return $back->withErrors(['discord' => __('This Discord account is already linked to another account.')]);
        }
        $audit->log('identity.discord_linked', null, ['discord_id' => $id], $request->user()->id);

        return $back->with('success', __('Your Discord account is linked.'));
    }

    public function unlink(Request $request, AuditLogger $audit): RedirectResponse
    {
        SocialIdentity::query()->where('user_id', $request->user()->id)->where('provider', 'discord')->delete();
        $audit->log('identity.discord_unlinked', null, [], $request->user()->id);

        return redirect()->to(route('account.show').'#discord')->with('success', __('Your Discord account was unlinked.'));
    }
}
