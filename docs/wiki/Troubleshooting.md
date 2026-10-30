# Troubleshooting

Common problems and how to fix them. When an error page appears, the exact reason is in
`storage/logs/laravel.log` (open it in File Manager and look at the last lines).

> Türkçe: [Sorun Giderme](Sorun-Giderme.md)

## Installer

### A hosting check says "Needs attention"

| Check | Fix |
|---|---|
| PHP 8.4+ | cPanel → **MultiPHP Manager**: select PHP 8.4 or newer for the domain. |
| PHP extensions | The missing ones are listed under the checks. Enable them in **Select PHP Version → Extensions**, or ask your host. |
| Application key | `.env` could not be created. Make sure the application folder is writable by your account (permissions `755` for folders), then reload the page. |
| Writable storage | `storage` and `bootstrap/cache` and everything inside them must be writable: `755` for folders, `644` for files. Never use `777`. |
| Writable .env file | The application folder or `.env` is not writable. Fix the permissions (`.env` should be `600` or `644`). |
| Production debug disabled | Set `APP_DEBUG=false` in `.env`. |

### `storage/app/setup-token.txt` does not exist

Open the installer page once; the file is written on the first visit. If it still does not appear,
`storage/app` is not writable (see above). After installation the file is deleted on purpose.

### "Invalid setup token"

Copy the whole 64-character line from `setup-token.txt`, without spaces. The form allows five attempts per
minute.

### "Could not connect to the database"

- The host is usually `localhost`; some providers give a separate host name.
- cPanel adds your account name as a prefix: use the full names, for example `account_vibecraft` and
  `account_user`.
- The user must be added to the database in **MySQL Databases** with **ALL PRIVILEGES**.
- Copy the password again; passwords with any characters are supported.

### "The database tables could not be created"

Use an **empty** database and make sure the user has all privileges. On very slow hosting, try again; the step
can safely be repeated on the same database.

### The installer page loads without styles or forms send you to `https://`

Open the site with the address you will really use. If the site is behind Cloudflare, use SSL mode
**Full (strict)**, not *Flexible*.

## The site

### "403 Forbidden" on every page

- Layout B (`public_html`): the `.htaccess` file was not moved with the other files (it is hidden in File
  Manager; turn on *Show Hidden Files*), or Apache's `mod_rewrite` is not available. Ask your host.
- Layout A: the document root must end in `/public`.

### "400 Unrecognized host"

After installation the site only answers on the address in `APP_URL` (protection against host header attacks).
If you moved to a new domain or switched between `www` and no `www`, update `APP_URL` in `.env`, then redirect
the other variant to it in cPanel **Redirects**.

### "500 Server Error"

Look at the end of `storage/logs/laravel.log`. Typical causes: a typo in `.env` (values with spaces need double
quotes), wrong file permissions after an upload, or a PHP version below 8.4 selected for the domain.

### Changes in `.env` have no effect

If you once ran `php artisan config:cache`, run `php artisan optimize:clear` after every change.

## Cron does not run

- The command must use the PHP **CLI** binary of version 8.4, for example
  `/opt/cpanel/ea-php84/root/usr/bin/php`. The path `/usr/local/bin/php` is often an older version.
- The path to `artisan` must be absolute: `/home/ACCOUNT/vibecraft/artisan`.
- Remove `>> /dev/null 2>&1` for one run and let cPanel e-mail you the output to see the error.

## E-mail does not arrive

- Check the `MAIL_` settings in `.env` ([First Steps](First-Steps.md#2-configure-e-mail)) and that cron runs:
  e-mails are sent by the queue job.
- `MAIL_FROM_ADDRESS` should be a mailbox on your own domain.
- Add SPF, DKIM and DMARC records for the domain (cPanel → **Email Deliverability**) so mail does not land in
  spam.

## Payments

A paid order still shows as pending: see [Payments → When a notification is late or fails](Payments.md#when-a-notification-is-late-or-fails).

## The plugin

### The plugin does not connect

- `enabled: true`, and `site` is exactly `https://your-domain` (no path, no trailing slash).
- `key-id` and `secret` are from the **latest** key rotation.
- The game server can reach the website over HTTPS (some hosts block outgoing connections; ask them).
- The game server's clock is correct (more than five minutes off is refused); enable NTP time sync.
- The server console shows the exact reason at startup. Offline mode, legacy BungeeCord forwarding and Folia
  are refused on purpose.

### A delivery does not arrive

| Status or message | Reason |
|---|---|
| Waiting while the player is offline | The command has **Player must be online**; it runs when they join |
| Stopped, command root not allowed | Add the command's first word to `allowed-command-roots` and restart |
| Uncertain | See [Paper Plugin → When a delivery is uncertain](Paper-Plugin.md#when-a-delivery-is-uncertain) |
| The product needs a linked account | The player links their Minecraft account first |

### `/vclink` says the code is invalid

Codes expire after 10 minutes and work once. Generate a new one on the **Account** page.

## Accounts

### A staff member lost their two-factor device

They sign in with one of their recovery codes and set up two-factor authentication again under
**Account → Security**.

### The owner cannot get in

See [Roles and Permissions → Lost owner access](Roles-and-Permissions.md#lost-owner-access).

## Still stuck?

Open an issue on [GitLab](https://gitlab.com/siberanka/vibecraft/-/issues) with the VibeCraft version, PHP
version, hosting type and the relevant lines from `laravel.log`. Remove e-mail addresses, keys and passwords
before posting. Report security problems privately ([SECURITY.md](../../SECURITY.md)).
