# Appearance and Themes

Change how your site looks without touching code. Every change applies immediately and is recorded in the
audit log.

> Türkçe: [Görünüm ve Temalar](Gorunum-ve-Temalar.md)

## Themes

**Admin → Themes** shows ten built-in themes. Switching themes keeps all content, accounts and orders.

| Theme | Made for |
|---|---|
| **Atlas** | Networks with many game modes |
| **Forge** | Survival and economy servers |
| **Volt** | Competitive and arena servers |
| **Nomad** | Adventure and RPG servers |
| **Mono** | Minimal sites and creators |
| **Prism** | A modern liquid-glass look: translucent panels over an aurora night, with a light that follows the pointer |
| **Arcade** | Retro pixel servers: pixel lettering, bevelled menu windows and buttons that press down |
| **Terminal** | A green phosphor console: monospace type, prompt-style headings and scanlines |
| **Brick** | A bright, friendly light theme: heavy outlines, hard shadows and flat colour blocks |
| **Dusk** | An eighties neon sunset: banded sky, glowing outlines and a grid to the horizon |

Each theme has its own layout, typefaces and original artwork; try them on your content before choosing.
Mono and Brick are light themes; the others are dark. Prism's glass uses background blur, which older
browsers replace with solid panels.

Screenshots of every theme are in the [README](../../README.md#screenshots).

## Small details

Every theme carries a few details of its own, each tied to one place and one job:

| Where | What it does |
|---|---|
| Home section headings | Servers, news and store each get their own small mark, drawn in the theme's style |
| Online servers | A "live" sign next to the player count: a beacon, an ember, a heart, an asterisk and so on |
| Copy IP button | A short theme-specific response when the address is copied |
| Prices, dates, error pages | Shaped like something from the theme's world (a coin, a price tag, bracketed dates, sticker numerals) |
| Top of the page | A thin reading-progress line in browsers that support scroll-driven animations |

There is also a hidden celebration: press ↑ ↑ ↓ ↓ ← → ← → B A on any page, or press the site name five times
quickly on the home page. Each theme answers differently (coins, shooting stars, digit rain, tumbling
blocks…) for a few seconds. Everything decorative is hidden from screen readers and switched off for visitors
who ask their device for reduced motion. To turn the celebration off, add `.theme-egg { display: none; }` to
**Custom CSS**.

## Theme colours

**Admin → Themes → Theme colours** lets you change the colour tokens of the active theme
(backgrounds, text, accent, buttons and borders) and the corner radius. **Restore theme defaults** brings back
the original palette. Choose colours with enough contrast; the theme's text and focus styles depend on them.

## Home sections

Under **Theme settings → Home sections** set the position of each home page section and whether it is shown:

| Section | Content |
|---|---|
| Status strip | Players online and quick links to servers, store, support and Discord |
| Servers | Your game servers with live player counts |
| Latest news | Newest news posts |
| Recent purchases | Latest store purchases (only if enabled in Settings) |
| Store | Featured products |

You can also hide built-in items from the main menu there: servers, store and cart, news, support, chest and
top up, and the community menu.

## Menus

**Admin → Menus** edits the **Header menu** and **Footer menu**. Each link has a label, an address (a site
path starting with `/` or an `https://` address), a position, **Visible** and **Open in a new tab**. Use it to
add your Discord, rules page, vote page or map.

## Custom CSS

**Admin → Custom CSS** adds your own CSS to the public site, for every theme. It never loads in the admin
panel, so a mistake cannot lock you out.

- The CSS is checked before it is saved. Images may only come from `/media/` or `/assets/`; outside addresses,
  imports and scripts are refused.
- **Ready-made snippets** add common Minecraft touches with one click: block-shadow headline, hotbar menu,
  inventory-slot images and pixel-notched cards.

## Image library

The image picker opens wherever an image is needed (logo, hero, categories, products, news covers and
more). It has three parts:

- **Image sets**: 142 original pixel-art icons in 14 packs, ready to use and free to use on your site: ranks
  and blank tags, spawners, economy, keys, crates, armour, swords, axes, pickaxes, tools, spawn eggs, potions
  and more. These cannot be deleted.
- **My uploads**: everything you uploaded.
- **Upload a new image**: JPEG, PNG or WebP, up to 4 MB and 4 million pixels. Add a short description for screen reader
  users.

**Admin → Media library** lists your uploads. An image that is not used anywhere can be deleted; if it is
still used, the panel shows where and asks you to replace it first.

Uploaded images are public files. Do not upload private documents.

## Logo and hero image

Set them under **Admin → Settings**. Without a logo the theme shows a block icon with your site name and
tagline. A good hero image is a wide screenshot of your server (at least 1600 pixels wide).

## Favicon

The browser tab icon is `public/favicon.ico`. Replace that file with your own icon in File Manager.

## Languages

The site, admin panel and e-mails come in Turkish and English. Visitors switch language with the language
button; the default comes from **Settings → Default language**. Wiki articles and legal documents are written
separately for each language.
