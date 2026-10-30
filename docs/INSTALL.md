# Installation and operations

VibeCraft requires PHP 8.4 or newer, MySQL 8 / MariaDB with InnoDB, and a cron job.
Enable ctype, curl, dom, fileinfo, filter, GD, hash, intl, mbstring, OpenSSL, PDO MySQL,
session, sodium, tokenizer and XML. SQLite is intended for isolated development tests.
Node, Redis and a persistent worker are not runtime requirements.

## cPanel layout

Keep the application outside public_html. Configure the domain's document root to
`/home/ACCOUNT/vibecraft/public`. The only public directory is `public/`.
Do not copy the entire application into public_html. If your host cannot change the
document root, use a separate domain/subdomain with the correct root or another host.

Upload reviewed source and production Composer dependencies. Never upload a development
environment, database dump, local credentials, private working notes, or development tooling.
Preserve third-party license files in vendor and public/assets/fonts.

Source archives deliberately omit vendor and development/runtime data. Install dependencies
from the included composer.lock using --no-dev before starting the application. Maintainers
can build a deterministic source archive from a clean checkout with Python3.11+ and
`python tools/package_source.py`; its manifest records every packaged file and source revision.
That command only writes a local artifact and does not publish or deploy anything.

## First installation

1. Create an empty database and a separate database account for this site.
2. Copy `.env.example` to `.env`. Set APP_URL to the exact public HTTPS origin,
   APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true and database credentials.
   Configure a working SMTP account; do not use the log mailer for real password resets.
3. In a trusted local build environment run `composer install --no-dev --prefer-dist --optimize-autoloader`.
   Transfer the resulting vendor directory if Composer is unavailable on the host.
4. Run `php artisan key:generate` once, then `php artisan migrate --force`.
   Keep the generated application key in an encrypted offline backup.
5. Run `php artisan vibecraft:setup-token` from cPanel Terminal or a one-time cPanel cron
   command. It prints a one-time token; keep that output private and remove the one-time cron.
6. Open `/install`, enter that token and create the owner account. No demo account is seeded.
   The installer closes after installation. Sign in and enable and confirm TOTP before admin access.
7. Set up the regular cron below and verify its last-run time. Enable only integrations for
   which credentials and provider sandbox checks have been completed.

Only storage and bootstrap/cache need write access by the PHP account. Never use 0777.
APP_KEY rotation requires an explicit decrypt/re-encrypt migration; replacing it casually
destroys access to encrypted integration and bridge secrets and two-factor credentials.

## Cron

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/vibecraft/artisan schedule:run >> /dev/null 2>&1
```

Select the actual PHP 8.4+ CLI executable provided by your host. Each run drains the database
queue with a bounded lifetime, refreshes cached server status, expires reservations, retries
verified payment events and reconciles the wallet ledger. Cron overlap is locked. A five-minute
host restriction increases delivery/retry latency and must be accepted explicitly by the operator.

## Payments and game servers

Configure payment secrets in the owner-only integration screen. Webhooks are
`/api/payments/stripe/webhook` and `/api/payments/paytr/webhook`; browser return URLs cannot
settle payments. The local sandbox gateway is unavailable in production.

Create game servers and product command templates. Bridge keys are shown once on creation
or rotation. Transfer them privately. Permit outbound HTTPS from the game server to this
site; public RCON is unnecessary. The bridge protocol is documented in `docs/BRIDGE.md`.
The development Paper adapter, its supported server/authentication modes, offline build and
durable-history recovery procedure are in `bridge/paper/README.md`. It is covered by the same VibeCraft
License; do not install it on an unsupported platform or discard its delivery journal.

## Updates and recovery

Before upgrading, take an encrypted database backup, storage backup, source release and
APP_KEY backup; test restoring them into a separate environment. Put the site into maintenance
mode, replace source and vendor from a reviewed release, migrate, clear/rebuild caches and
resume traffic. Restore the matching database snapshot for rollback if migrations are not
reversible. Never restore source alone after incompatible schema changes.

Lost owner access can be recovered offline with `php artisan vibecraft:grant-owner EMAIL`.
This does not bypass TOTP; protect console/cron access. Interrupted installation after an owner
was created requires offline investigation and recovery, rather than exposing a new claim flow.

The release acceptance matrix must be completed on the actual host before accepting real money.
Local Apache/PHP tests demonstrate a compatible profile, not cPanel certification.

Configure a real mail transport before opening registration or password recovery. Verify
both verification and reset messages using the actual SMTP host, certificate and recipient
mailbox. `MAIL_TIMEOUT` bounds an SMTP operation (default: 10 seconds); a loopback mail-catcher
test alone does not prove provider authentication, remote TLS or inbox delivery.
