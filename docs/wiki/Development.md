# Development

Running VibeCraft from source, testing changes and building the release files.

> Türkçe: [Geliştirme](Gelistirme.md)

## Requirements

- PHP 8.4+ with the extensions from [Installation](Installation.md#what-you-need)
- Composer 2
- MySQL 8 or MariaDB (SQLite in memory is used by the test suite)
- For the plugin: JDK 25 and a POSIX shell

No Node toolchain is used: CSS and JavaScript in `public/assets` are written by hand.

## Run locally

```sh
git clone https://gitlab.com/siberanka/vibecraft.git
cd vibecraft
composer install
cp .env.example .env
php artisan key:generate
```

In `.env` set `APP_ENV=local`, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false` and your local
database, then:

```sh
php artisan migrate
php artisan vibecraft:setup-token
php artisan serve --host=127.0.0.1
```

Open `http://localhost:8000/install`, enter the printed token and create the owner. There is no default user.
For local work keep `VIBECRAFT_INSTALLER_WRITES_ENV=false` if you do not want the installer to edit `.env`.

## Project layout

| Path | Contents |
|---|---|
| `app/Core` | Installation, settings, security, audit log, themes, admin catalogue |
| `app/Domain` | Business logic: commerce (orders, wallet, deliveries), payments, bridge, Minecraft, content, legal, media, CAPTCHA |
| `app/Http` | Controllers and middleware |
| `resources/themes/<id>` | Theme manifests (`theme.json`) |
| `resources/views/themed` | Blade views shared by all themes |
| `resources/lang` | `en.json` and `tr.json` translations |
| `public/assets` | CSS, JavaScript, fonts, theme artwork and the icon library |
| `routes` | Web, API (payments and bridge) and scheduled jobs (`console.php`) |
| `bridge/paper` | The Paper plugin (Java, no Gradle or Maven) |
| `tests` | PHPUnit feature and unit tests |
| `tools` | Release, source packaging and icon library scripts |

## Tests and checks

```sh
php artisan test
php -d opcache.enable_cli=0 vendor/bin/phpstan analyse --memory-limit=256M --no-progress
vendor/bin/pint --test
```

All three must pass before a change is merged. New behaviour comes with a test; security fixes come with a
regression test.

## Translations

Every user-facing text is written in English in the code and translated in `resources/lang/tr.json`. When you
add a text, add its Turkish translation in the same change. Admin setting labels use their setting key
(for example `store.gifts`) and need an entry in both `en.json` and `tr.json`.

## Building the plugin

Place the six pinned API jars listed in `bridge/paper/dependencies.sha256` in one folder (download links and
coordinates are in [bridge/paper/README.md](../../bridge/paper/README.md)), then:

```sh
cd bridge/paper
sh build.sh /absolute/path/to/provided-api-jars
```

The script checks every jar's SHA-256, compiles, runs the core tests and writes
`target/VibeCraftBridge-<version>.jar` containing only the plugin's own classes. Optional offline lifecycle
tests with MockBukkit are described in the plugin README.

## Building a release

From a clean checkout (nothing uncommitted):

```sh
python tools/build_release.py --php /path/to/php8.4 --composer /path/to/composer.phar --jar bridge/paper/target/VibeCraftBridge-<version>.jar
```

It takes the committed files only (`git archive`), leaves out tests, tools and the plugin source, installs the
production Composer dependencies and refuses to package environment files, databases, dumps, logs or keys.
The output in `build/release/` is the hosting zip, the plugin jar and `SHA256SUMS`.

Before publishing, install the zip on a test host in both folder layouts and check that the protected paths
listed in [Security](Security.md#built-in) return 403 or 404. Releases are built and uploaded by hand; the
project uses no CI.

## Versioning

The version lives in `config/vibecraft.php` and in the plugin (`bridge/paper/build.sh`, `plugin.yml`,
`Protocol.java`). Bump them together. Tags are `vMAJOR.MINOR.PATCH`, with `-beta` for pre-releases.

## Contributing and license

Bug reports and merge requests are welcome on [GitLab](https://gitlab.com/siberanka/vibecraft). VibeCraft is
distributed under the [VibeCraft License](../../LICENSE): copies and forks must keep the license, the
[NOTICE](../../NOTICE) and the attribution, and may not be sold or offered as a paid service.
