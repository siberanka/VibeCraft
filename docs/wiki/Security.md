# Security

What VibeCraft protects by itself, and what you as the site owner need to take care of.

> Türkçe: [Güvenlik](Guvenlik.md)

## Built in

| Area | Protection |
|---|---|
| Installation | A fresh upload can only be installed with the one-time token from the hosting files. The installer closes for good after setup. |
| Files | `.env`, `storage`, `vendor`, source code, archives (`.zip`, `.tar.gz`…), database dumps (`.sql`), backups (`.bak`, `~`), logs and hidden files are refused by the web server, in both folder layouts. Only `index.php` runs as PHP. |
| Accounts | Passwords are hashed; sign-in, registration and reset forms are rate limited; optional CAPTCHA; TOTP two-factor authentication, required for staff. |
| Sessions | Encrypted, `HttpOnly`, `SameSite`, secure cookies on HTTPS. |
| Pages | Strict Content Security Policy with per-request nonces, no inline scripts; user content is escaped; Markdown is rendered without raw HTML. |
| Payments | Only signed provider notifications change an order; amounts are checked against the order; every balance change is a unique ledger entry. |
| Game servers | Signed requests with per-server keys; the plugin calls out, no open port; command roots must be allowed on the server; only typed placeholders reach commands. |
| Secrets | Payment, CAPTCHA and Discord secrets are stored encrypted, never shown again and never written to the audit log. |
| Staff | Two-factor authentication, password re-confirmation and an audit log for every admin change. |

## Your checklist

- **HTTPS** on the domain (cPanel AutoSSL). Turn on **Require HTTPS (HSTS)** in Settings only after HTTPS works
  on the domain and all subdomains.
- Prefer the **document root on `public/`** layout. The `public_html` layout is safe on Apache with
  `mod_rewrite`, but keeps more files inside the web folder.
- **Delete the release zip** after extracting it, and never upload database exports or backups into the site
  folder.
- Keep **`APP_DEBUG=false`** in `.env` on a live site.
- Use **strong, unique passwords** for cPanel, the database, mail and the owner account; turn on two-factor
  authentication in cPanel too.
- Give staff the **smallest role** that fits ([Roles and Permissions](Roles-and-Permissions.md)).
- In the plugin, allow only the **command roots** your products need.
- Keep VibeCraft, PHP and your server plugins **up to date**.

## CAPTCHA

**Admin → Integrations**: choose **Cloudflare Turnstile**, **hCaptcha** or **Google reCAPTCHA** and enter the
site key and secret from the provider. The CAPTCHA then protects sign-in, registration, password reset, support
tickets, credit codes, staff applications and the forum. If the CAPTCHA service cannot be reached, those forms
are refused rather than left unprotected.

In the provider's dashboard allow only your own domain.

## Cloudflare

If your site is behind Cloudflare's proxy (orange cloud):

1. Use SSL mode **Full (strict)**.
2. Add `VIBECRAFT_PROXY_MODE=cloudflare` to `.env`. VibeCraft then trusts the visitor IP from Cloudflare only when
   the request really comes from a Cloudflare address (the address list is refreshed weekly by cron). Without
   this setting every visitor appears to come from Cloudflare, and rate limits would hit everyone together.
3. Do **not** cache sign-in, account, admin, cart, checkout, orders, tickets or `/api/` addresses.
4. Exclude the payment webhooks (`/api/payments/…`) and the plugin address (`/api/bridge/…`) from bot
   challenges ("Under Attack", Bot Fight Mode, managed challenges). They are protected by their own signatures.

Behind another reverse proxy, use `VIBECRAFT_PROXY_MODE=custom` and list the proxy addresses in
`VIBECRAFT_TRUSTED_PROXIES` (comma separated CIDRs).

## Backups

Back up regularly, encrypted and outside the web folder:

| What | Why |
|---|---|
| The database | All accounts, orders, balances and content |
| `storage/app` | Uploaded images and order copies |
| `.env` | The application key: without it encrypted secrets and two-factor settings cannot be read |
| `plugins/VibeCraftBridge/receipts.vcj` on each game server | The delivery journal; restore it together with the matching database backup |

Test a restore on a separate site from time to time. cPanel **Backup** or **JetBackup** can cover the first
three.

## Never change the application key casually

`APP_KEY` in `.env` encrypts payment and plugin secrets and two-factor settings. Replacing it makes them
unreadable. If it ever leaks, plan the rotation as described in [docs/INSTALL.md](../INSTALL.md).

## Reporting a vulnerability

Please do not open a public issue. Report privately as described in [SECURITY.md](../../SECURITY.md).

More technical detail: [docs/SECURITY.md](../SECURITY.md).
