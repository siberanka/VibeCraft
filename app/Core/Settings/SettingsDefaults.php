<?php

namespace App\Core\Settings;

final class SettingsDefaults
{
    /** @var array<string, mixed> */
    public const VALUES = [
        'site.name' => 'VibeCraft',
        'site.hero_title' => '',
        'site.hero_media_id' => null,
        'site.tagline' => 'Minecraft topluluğu',
        'site.description' => '',
        'site.java_address' => '',
        'site.bedrock_address' => '',
        'site.bedrock_port' => 19132,
        'site.logo_media_id' => null,
        'site.default_locale' => 'tr',
        'site.footer_note' => '',
        'site.season_label' => '',
        'site.season_ends_at' => null,
        'site.announcement' => '',

        'social.discord_invite' => '',
        'social.youtube' => '',
        'social.instagram' => '',
        'social.tiktok' => '',
        'social.x' => '',

        'theme.active' => 'atlas',

        'modules.store' => true,
        'modules.news' => true,
        'modules.tickets' => true,
        'modules.wiki' => true,

        'auth.registration' => true,
        'auth.require_email_verification' => false,

        'legal.enforce' => false,
        'legal.market_selection' => 'operator',
        'legal.default_market_id' => 0,
        'legal.selection_revision' => 1,

        'security.require_admin_2fa' => true,
        'security.hsts' => false,

        'store.wallet_label' => 'Kredi',
        'store.terms_page' => null,
        'store.show_recent_purchases' => false,
        'store.gifts' => true,
        'store.allow_direct_checkout' => true,

        'payments.sandbox.enabled' => false,
        'payments.stripe.enabled' => false,
        'payments.stripe.publishable_key' => '',
        'payments.paytr.enabled' => false,
        'payments.paytr.merchant_id' => '',
        'payments.paytr.test_mode' => true,

        'captcha.provider' => 'none',
        'captcha.site_key' => '',
        'captcha.forms' => ['login', 'register', 'forgot_password', 'ticket', 'gift_code', 'application', 'forum'],
        'captcha.fail_mode' => 'closed',

        'discord.client_id' => '',
        'discord.notify_orders' => true,
        'discord.notify_tickets' => true,
    ];

    public static function get(string $key): mixed
    {
        return self::VALUES[$key] ?? null;
    }
}
