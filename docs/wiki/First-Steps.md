# First Steps

What to do right after the [installation](Installation.md), in this order.

> Türkçe: [İlk Adımlar](Ilk-Adimlar.md)

## 1. Turn on two-factor authentication

The admin panel (`/admin`) is only open to staff accounts with two-factor authentication. Sign in, open
**Account → Security**, scan the QR code with an authenticator app (Aegis, 2FAS, Google Authenticator,
Microsoft Authenticator and similar) and confirm with a code. Store the recovery codes somewhere safe and
offline.

The admin panel also asks for your password again from time to time before sensitive pages.

The admin menu has six areas: **Overview**, **Store**, **Content**, **Community**, **Appearance** and
**System**. Pressing an area shows its pages; you only see the areas your role may open. This wiki names the
page itself, for example "Admin → Settings" (under System) or "Admin → Products" (under Store).

## 2. Configure e-mail

Registration e-mails, password resets and order copies need a real mail server. The installer does not ask
for it, so edit `.env` in File Manager (it is in the application folder, next to `artisan`):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.your-domain.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@your-domain.com
MAIL_PASSWORD="your mailbox password"
MAIL_FROM_ADDRESS="no-reply@your-domain.com"
MAIL_FROM_NAME="Your Server"
```

Create the mailbox first in cPanel → **Email Accounts**. Use port `587` with `MAIL_SCHEME=smtp` if your host
requires STARTTLS. Put values that contain spaces or `#` in double quotes. Test with "Forgot password" on
the sign-in page before opening registration to players.

`.env` holds your application key and passwords: keep a private copy and never share it.

## 3. Fill in the site settings

**Admin → Settings**:

| Setting | Notes |
|---|---|
| Community name, home headline, tagline, community description | Shown in the header, on the home page and in search results |
| Java server address, Bedrock server address, Bedrock port | Shown with a copy button on the home page |
| Logo image, home image | Chosen from the [image library](Appearance-and-Themes.md#image-library) |
| Default language | Turkish or English; visitors can switch |
| Header announcement, footer note | Short text across the site |
| Discord invite link | An `https://` invite link |
| Store, news, support and wiki modules | Turn each part of the site on or off |
| New registrations, require email verification | Keep e-mail verification on once mail works |
| Require two-factor authentication for staff | On by default; keep it on |
| Require HTTPS (HSTS) | Turn on only after HTTPS works on the domain and all its subdomains |
| Balance name | The name of your site currency, for example "Credit" |
| Show recent purchases, players can send gifts | Store options, see [Store](Store.md#store-options-in-settings) |

## 4. Add your game servers

**Admin → Servers → New**: name, Java address, optional Bedrock address and port, and the status query host
and port (usually the same address and `25565`). Tick **Show on site** to list it on `/servers`; the player
count refreshes every two minutes through cron.

To deliver purchases in game, install the plugin next: [Paper Plugin](Paper-Plugin.md).

## 5. Pick a theme

**Admin → Themes**: choose Atlas, Forge, Volt, Nomad or Mono, then adjust colours, home sections and menus in
**Theme settings** and **Menus**. See [Appearance and Themes](Appearance-and-Themes.md).

## 6. Publish your legal documents

Before selling, publish your terms, privacy and sales documents under **Admin → Legal documents**. Sample
text is not legal advice; the documents must name your real business. Details are in
[docs/LEGAL.md](../LEGAL.md) (Turkish).

## 7. Set up the store and payments

Create categories and products, attach the console commands and connect a payment provider:
[Store](Store.md) and [Payments](Payments.md).

## 8. Invite your staff

Staff register like players. Give them a role under **Admin → Users**; see
[Roles and Permissions](Roles-and-Permissions.md). Every staff member must enable two-factor
authentication before the admin panel opens for them.

## Before taking real money

Go through [docs/RELEASE_CHECKLIST.md](../RELEASE_CHECKLIST.md) on your real hosting: a test purchase with
the payment provider's test mode, a delivery on your server, a refund and a restore from backup.
