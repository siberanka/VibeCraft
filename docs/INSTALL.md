# Installation and operations

VibeCraft requires PHP 8.4 or newer, MySQL 8 / MariaDB with InnoDB, and a cron job.
Enable ctype, curl, dom, fileinfo, filter, GD, hash, intl, mbstring, OpenSSL, PDO MySQL,
session, sodium, tokenizer and XML. SQLite is intended for isolated development tests.
Node, Redis and a persistent worker are not runtime requirements.

## Quick install on cPanel (recommended)

1. Download `vibecraft-<version>.zip` from the latest release (GitHub or GitLab) and check its
   SHA-256 against `SHA256SUMS`. The archive already contains the production dependencies.
2. In cPanel open **MultiPHP Manager** and select PHP 8.4 or newer for the domain. In **Select PHP
   Version / PHP Extensions** enable the extensions listed above.
3. In **MySQL Databases** create an empty database and a user, and add the user to the database
   with all privileges.
4. Upload the zip with **File Manager** and extract it. Then choose one layout:
   - **Recommended:** extract to `/home/ACCOUNT/vibecraft` (outside `public_html`) and set the
     domain's document root to `/home/ACCOUNT/vibecraft/public` in **Domains**.
   - **If the document root cannot be changed:** move the *contents* of the extracted `vibecraft`
     folder into `public_html`. The included `.htaccess` routes every request into `public/` and
     refuses everything else (including `.env`, `storage`, `vendor` and any archive or backup file).
5. Delete the uploaded zip file after extracting it.
6. Open `https://your-domain/` in the browser. The installer starts automatically:
   - **Setup token:** open `storage/app/setup-token.txt` in File Manager and paste its content.
     Only someone with access to the hosting files can read it, so nobody else can claim a fresh site.
   - **Database:** enter the site address and the database details. The installer writes `.env`,
     keeps the generated application key and creates all tables.
   - **Owner account:** create the owner. The token files are deleted and the installer closes.
7. In **Cron Jobs** add the command shown on the last installer step, every minute, for example:
   `* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/ACCOUNT/vibecraft/artisan schedule:run >> /dev/null 2>&1`
8. Sign in, enable two-factor authentication, then configure mail (SMTP), payments and game servers
   in the admin panel. Keep a private copy of `.env` (it holds the application key).

## Manual install (terminal)

Keep the application outside public_html and set the domain's document root to
`/home/ACCOUNT/vibecraft/public`. From a source checkout run
`composer install --no-dev --prefer-dist --optimize-autoloader`, copy `.env.example` to `.env`,
set APP_URL, database and mail settings, run `php artisan key:generate`,
`php artisan migrate --force` and `php artisan vibecraft:setup-token`, then open `/install` and
enter the printed token. Never upload a development environment, database dump, local credentials
or development tooling, and preserve third-party license files in vendor and public/assets/fonts.

Maintainers build the release archive from a clean checkout with
`python tools/build_release.py --php PHP --composer COMPOSER_PHAR`; it packages only committed files
plus production dependencies and refuses environment files, dumps, logs and keys.

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
