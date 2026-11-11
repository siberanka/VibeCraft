# Appearance and Themes

Change how your site looks without touching code. Every change applies immediately and is recorded in the
audit log.

> Türkçe: [Görünüm ve Temalar](Gorunum-ve-Temalar.md)

## Themes

**Admin → Appearance → Themes** shows the ten built-in themes as cards with a preview, the current colours and
whether the theme is light or dark. Switching themes keeps all content, accounts and orders.

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

## Pointer effects

Every theme has its own live effect that follows the mouse and answers clicks. On phones and tablets it
answers taps instead.

| Theme | Effect | When you click or tap |
|---|---|---|
| Atlas | Ripples: small motes rise from the path | Rings spread like water |
| Forge | Embers drift upward | Sparks fly as from an anvil |
| Volt | Lightning crackles along the path | Bolts in every direction |
| Nomad | Footprints, left and right | A puff of sand |
| Mono | A quiet ring trails the cursor and opens over links | A small cross is stamped |
| Prism | Light splits into rose, white and sky | Glass shards |
| Arcade | Pixels on a grid | Coins jump and fall |
| Terminal | Characters on a text grid | A blinking block cursor and a scan line |
| Brick | Toy blocks tumble off the path | A handful of blocks flies up |
| Dusk | A neon ribbon fading from pink to cyan | A neon ring and a few stars |

The effect reacts to what the mouse is doing:

- Over links and buttons it becomes brighter; clicking something that cannot be used gives a small muted
  response instead.
- It pauses over form fields, while text is being selected, and when the mouse rests or leaves the window.
- The normal mouse cursor is never replaced, so the text, link, waiting and "not allowed" cursors keep working.
- On touch screens only short, still taps trigger it; scrolling and long presses do not.
- Visitors whose device asks for reduced motion never see it, and it never runs in the admin panel.

**Admin → Appearance → Theme settings → Pointer effects** sets it for each theme:

| Setting | Choices |
|---|---|
| Effect | The theme's own effect, any of the other nine, or **Off** |
| Strength | Subtle, Normal or Lively (how many marks and how big the bursts are) |
| Tap effects on phones and tablets | On or off |

Try your choice in the preview area on the same page before saving. The effect uses the theme's colours, so a
different palette changes it too.

## Theme colours

**Admin → Appearance → Theme colours** (or **Colours** on a theme card) has two parts:

- **Ready palettes**: every theme comes with several palettes made for it. One press applies a palette;
  **Theme default** brings the original colours back. The palette in use is marked.
- **Your own colours**: change any colour (backgrounds, text, accent, focus, borders) and the corner radius
  yourself, starting from the default or from a ready palette.

Text colours are checked when you save: a combination that would be hard to read is refused with a message
that names the two colours. Each theme keeps its own colours, so you can prepare several themes and switch
between them.

## Home artwork

**Admin → Appearance → Theme settings** → **Home artwork** decides the picture on the home page of each theme:

| Choice | Result |
|---|---|
| Theme picture | The original artwork that comes with the theme |
| My own image | An image from your uploads or the image library; a wide picture of at least 1600 pixels works best |
| No picture | The home page without a picture |

Choosing **Theme picture** again restores the original at any time. The choice is saved per theme. The
**Home image** in Settings is still used for every theme that keeps "Theme picture", so one image can cover
all themes. An image used as home artwork is listed as "in use" in the media library and cannot be deleted
by accident.

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
