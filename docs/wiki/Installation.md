# Installation

This page installs VibeCraft on shared hosting with cPanel. Other panels (Plesk, DirectAdmin, CyberPanel)
work the same way as long as they run Apache with `.htaccess` support; only the menu names differ.

> Türkçe: [Kurulum](Kurulum.md)

## What you need

- A domain or subdomain with HTTPS (cPanel AutoSSL is enough).
- PHP 8.4 or newer with these extensions: ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl,
  pdo_mysql, tokenizer, xml (and the usual filter, hash, session, sodium).
- An empty MySQL 8 or MariaDB database.
- Access to the cPanel **File Manager** and **Cron Jobs**.

No terminal, Node, Redis or background worker is needed.

## 1. Download and check the package

Download from the [releases page](https://gitlab.com/siberanka/vibecraft/-/releases)
(mirror: [GitHub](https://github.com/siberanka/VibeCraft/releases)):

| File | Use |
|---|---|
| `vibecraft-<version>.zip` | The website, with all PHP dependencies included |
| `VibeCraftBridge-<version>.jar` | The Paper plugin (see [Paper Plugin](Paper-Plugin.md)) |
| `SHA256SUMS` | Checksums |

The "Source code" archives that the platforms add automatically do **not** include the dependencies; do not
upload those to your hosting.

To check the download on Linux or macOS run `sha256sum -c SHA256SUMS`; on Windows run
`certutil -hashfile vibecraft-<version>.zip SHA256` and compare the result with the line in `SHA256SUMS`.

## 2. Select PHP 8.4

1. cPanel → **MultiPHP Manager**: tick the domain and choose PHP 8.4 (or newer).
2. cPanel → **Select PHP Version** → **Extensions** (on CloudLinux hosts): make sure the extensions
   listed above are ticked. Hosts without this screen usually enable them already.

## 3. Create the database

1. cPanel → **MySQL Databases** → *Create New Database*, for example `account_vibecraft`.
2. *Add New User* with a strong password.
3. *Add User To Database* → tick **ALL PRIVILEGES**.

Write down the database name, the user name and the password; the installer asks for them. The host is
normally `localhost`, the port `3306`.

## 4. Upload the files

Choose one of the two layouts.

### Layout A (recommended): document root on `public/`

1. File Manager → open your home folder (`/home/ACCOUNT`), upload the zip and **Extract** it. A folder named
   `vibecraft` appears.
2. cPanel → **Domains** → *Manage* the domain → set **Document Root** to `vibecraft/public`.

Only the `public` folder is reachable from the web; the application code, `.env` and `storage` stay outside.

### Layout B: everything inside `public_html`

Use this when the document root cannot be changed (for example the main domain on some hosts).

1. Upload the zip into `public_html` and extract it.
2. Open the new `vibecraft` folder, select all files (**Select All**, and make sure hidden files such as
   `.htaccess` are shown: *Settings → Show Hidden Files*), **Move** them to `public_html`, then delete the
   empty `vibecraft` folder.

The `.htaccess` file in the package sends every request into `public/` and refuses everything else,
including `.env`, `storage`, `vendor`, archives, database dumps and backup files. If Apache's `mod_rewrite`
is missing, the site refuses all requests instead of exposing files.

### Afterwards

**Delete the uploaded zip file.** It is not reachable from the web in either layout, but it has no further
use on the server.

## 5. Run the browser installer

Open `https://your-domain/`. The installer starts automatically and shows the **Hosting checks** on the
right. Every line must say **Ready**; the [Troubleshooting](Troubleshooting.md) page explains each check.

### Step 1 — Setup token

The installer writes a one-time code to `storage/app/setup-token.txt`. Open that file in File Manager
(right click → *View* or *Edit*), copy the 64-character code and paste it into the form.

Only someone with access to your hosting files can read this file, so nobody else can take over a freshly
uploaded site before you finish.

### Step 2 — Database

| Field | Value |
|---|---|
| Site address | The exact address players will open, for example `https://play.example.com` (no path, no trailing slash) |
| Database host | `localhost` (or the host your provider gives) |
| Port | `3306` |
| Database name / user / password | From step 3 |

The installer tests the connection, writes the settings to `.env` and creates all tables. This can take
up to a minute on slow hosting. Use an empty database; the step stops if the tables cannot be created.

### Step 3 — Owner account

Enter the community name, the owner's user name (3–24 letters, numbers or `_`), e-mail and a password of at
least 12 characters with letters and numbers. When you submit:

- the owner account is created with every permission,
- the setup token files are deleted,
- the installer closes for good (`/install` returns "not found"),
- sessions and cache move to the database.

You are sent to the sign-in page.

## 6. Add the cron job

The last installer step shows the exact command for your hosting, for example:

```
* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/ACCOUNT/vibecraft/artisan schedule:run >> /dev/null 2>&1
```

cPanel → **Cron Jobs** → *Add New Cron Job* → *Once Per Minute* and paste the command (without the five
leading `*` fields, which the form fills in). The PHP path must point to the PHP **8.4 CLI** binary; ask your
provider if you are unsure. Without cron, deliveries, payment retries, server status and order expiry do
not run.

## 7. Continue with the first steps

Sign in and follow [First Steps](First-Steps.md): enable two-factor authentication, configure e-mail and
fill in your server details.

## Manual install with a terminal

For a VPS or a host with SSH, see the "Manual install" section of
[docs/INSTALL.md](../INSTALL.md). The release zip works there too; a source checkout needs Composer.

## nginx

The package protection relies on Apache `.htaccess`. On nginx, set `root` to the `public` directory, send
unknown paths to `index.php`, and deny hidden files and archive/backup extensions in the server block.
