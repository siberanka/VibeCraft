# Updates and Maintenance

Keeping a running site healthy and moving to a new version.

> Türkçe: [Güncelleme ve Bakım](Guncelleme-ve-Bakim.md)

## What cron does

The single cron line runs `artisan schedule:run` every minute, which starts these jobs:

| Job | How often | What it does |
|---|---|---|
| Queue | Every minute | Sends e-mails and Discord notifications, processes background work (stops after 50 seconds) |
| Expiry | Every minute | Cancels unpaid orders after 60 minutes, renews or expires timed ranks |
| Contract notices | Every minute | Queues order copies by e-mail |
| Server status | Every 2 minutes | Refreshes player counts of your game servers |
| Payment retries | Every 5 minutes | Re-processes verified payment notifications that failed |
| Clean-up | Hourly | Removes expired temporary data and password reset tokens |
| Ledger check | Daily 04:10 | Checks that wallet balances match the ledger |
| Failed job clean-up | Daily | Removes failed jobs older than 30 days |
| Cloudflare addresses | Weekly | Refreshes Cloudflare's IP list (only in Cloudflare mode) |

Jobs never overlap, so a slow run cannot pile up. If cron stops, players notice first in missing deliveries and
e-mails; check the command and PHP path in cPanel **Cron Jobs** (see [Troubleshooting](Troubleshooting.md#cron-does-not-run)).

## Updating to a new version

Read the release notes first; they say whether the update changes the database.

1. **Back up** the database, `storage/app`, `.env` and the plugin journal ([Security](Security.md#backups)).
2. Download the new `vibecraft-<version>.zip` and check it with `SHA256SUMS`.
3. **Replace the files, keep your data.** With layout A (document root on `public/`):
   - extract the new zip next to the old folder, for example as `vibecraft-new`,
   - move `.env` and the whole `storage` folder from the old folder into `vibecraft-new` (replace the empty
     `storage` folder of the package),
   - rename the old folder to `vibecraft-old` and `vibecraft-new` to `vibecraft`. The document root stays
     `vibecraft/public`.

   With layout B (`public_html`): extract the new zip into a temporary folder and copy its contents over
   `public_html`, without touching `.env` and `storage`.
4. **Update the database.** In cPanel open **Terminal** (or add a one-time cron job) and run:

   ```
   cd ~/vibecraft && php artisan migrate --force && php artisan optimize:clear
   ```

   Use the same PHP path as in your cron line. Remove a one-time cron job after it has run.
5. Open the site, sign in and check the store, a product page and **Admin → Deliveries**.
6. Update the plugin jar on every game server if the release contains a new one (stop, replace, start;
   keep the plugin folder).
7. Keep `vibecraft-old` until you are sure, then delete it.

If something goes wrong, rename the folders back and restore the database backup that matches the old files.
Never run old files against a database that a newer version has already changed.

## Console commands

Run these from cPanel **Terminal** or SSH inside the application folder (`php artisan …`):

| Command | Use |
|---|---|
| `vibecraft:setup-token` | Writes a new setup token (only before installation is complete) |
| `vibecraft:grant-owner EMAIL` | Gives the owner role to an existing account ([lost access](Roles-and-Permissions.md#lost-owner-access)) |
| `vibecraft:status-refresh` | Refreshes server status now |
| `vibecraft:expire` | Runs order and rank expiry now |
| `vibecraft:webhooks-retry` | Retries failed payment notifications now |
| `vibecraft:ledger-reconcile` | Checks the wallet ledger now |
| `vibecraft:prune` | Removes expired temporary data |
| `down` / `up` | Puts the site into maintenance mode and back |
| `optimize:clear` | Clears cached configuration, routes and views after manual changes |

## Logs

Errors are written to `storage/logs/laravel.log` (not reachable from the web). Open it in File Manager when
something fails. VibeCraft keeps secrets out of it, but it can contain e-mail and IP addresses: do not post it
publicly; share only the relevant lines.
