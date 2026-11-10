<?php

return [

    'version' => '0.9.0-beta',

    /*
    | Marker written by the installer. Until it exists every web request is
    | routed to the installer; afterwards the installer routes return 404.
    */
    'install_lock' => storage_path('app/installed.lock'),

    // The browser installer writes the database settings into .env (disabled in the test suite).
    'required_extensions' => ['ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'tokenizer', 'xml'],

    'installer_writes_env' => (bool) env('VIBECRAFT_INSTALLER_WRITES_ENV', true),

    /*
    | Trusted reverse proxy handling.
    |  - none:       REMOTE_ADDR is the client (direct origin, most cPanel setups)
    |  - cloudflare: CF-Connecting-IP is accepted only when REMOTE_ADDR is a Cloudflare edge address
    |  - custom:     X-Forwarded-For is accepted only from the listed proxy CIDRs
    */
    'proxy' => [
        'mode' => env('VIBECRAFT_PROXY_MODE', 'none'),
        'custom_cidrs' => array_filter(explode(',', (string) env('VIBECRAFT_TRUSTED_PROXIES', ''))),
    ],

    'store' => [
        'currency' => env('VIBECRAFT_CURRENCY', 'TRY'),
        // Unpaid orders are cancelled after this many minutes.
        'order_ttl_minutes' => 60,
        // Upper bound for one wallet top-up, in minor units.
        'max_topup_minor' => 1_000_000,
        'min_topup_minor' => 100,
    ],

    'bridge' => [
        // Accepted clock skew for signed requests.
        'max_skew_seconds' => 300,
        // How long a polled delivery stays reserved before it is handed out again.
        'lease_seconds' => 120,
        'poll_batch' => 25,
        'link_code_ttl_minutes' => 10,
    ],

    'status' => [
        'timeout_seconds' => 3,
        'refresh_minutes' => 2,
        'batch_limit' => 20,
        'batch_budget_seconds' => 20,
    ],

    'uploads' => [
        'max_kilobytes' => 4096,
        'max_pixels' => 16_000_000,
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    ],

    'themes' => ['atlas', 'forge', 'volt', 'nomad', 'mono', 'prism', 'arcade', 'terminal', 'brick', 'dusk'],

    'setup_token_file' => storage_path('app/setup-token.sha256'),

    'locales' => ['tr', 'en'],
];
