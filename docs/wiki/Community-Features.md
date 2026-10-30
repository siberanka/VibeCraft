# Community Features

Everything besides the store that keeps players coming back. News, the store, support tickets and the wiki
can each be switched off in **Admin → Settings → Modules**.

> Türkçe: [Topluluk Özellikleri](Topluluk-Ozellikleri.md)

## News

**Admin → News**: title, category, text in Markdown, cover image, status (draft or published) and a
publish time. Posts appear on `/news` and in the **Latest news** home section. A publish time in the future
schedules the post.

## Pages

**Admin → Pages**: standalone pages such as rules, about or staff team, written in Markdown. Tick
**Show in navigation** to add the page to the menu; otherwise link it from [Menus](Appearance-and-Themes.md#menus).

## Server guide (wiki)

A built-in guide at `/wiki` for commands, ranks, rules and how-tos.

- **Admin → Wiki categories** and **Wiki articles**. Categories and articles are written per language.
- Articles are Markdown, can be nested up to three levels and get a table of contents from their headings.
- Every save keeps a revision. **Revision history** shows earlier versions; reviewing one fills the editor
  without changing the live article.
- **Reader access**: everyone, verified members, or selected roles, per category and per article.
- Up to ten **frequently asked questions** per article, shown as an accordion.
- Signed-in readers can mark an article as helpful or not; only staff see the totals.

The full reference (in Turkish) is [docs/WIKI.md](../WIKI.md).

## Forum

**Admin → Forum categories** creates the boards. **Only staff start topics** turns a board into an
announcement board where players can still reply. Players with a verified e-mail open topics and reply;
staff with *forum moderation* can pin, lock and hide topics and hide single posts. Every moderation action is
written to the audit log.

## Support tickets

Players open tickets at `/tickets` with a subject and message and follow the replies there. Staff answer under
**Admin → Support**. New tickets can also be announced in Discord (see below).

## Staff applications

**Admin → Staff positions**: name, description, up to ten questions (one per line) and **Accepting
applications**. Open positions are listed at `/apply`. Staff with the *review applications* permission
read and decide under **Admin → Staff applications**; the player sees the result on the same page.

## Vote sites

**Admin → Vote sites**: name, vote link (`https://`), hours between votes and visibility. The `/vote` page lists
them with how often players can vote. Rewards for voting are given on the game server by your vote plugin.

## Leaderboards and punishments

- `/leaderboards` shows playtime, player kills, deaths and mob kills.
- `/punishments` shows the server's bans.

Both are filled by the [Paper plugin](Paper-Plugin.md#statistics-and-bans) when `share-statistics` and
`share-punishments` are turned on.

## Player profiles and badges

Every player has a profile at `/player/<name>` with their linked Minecraft account, join date, forum posts
and badges. Staff with *user management* create badges (name, colour, description) at `/admin/badges` and
award them on the user's page under **Admin → Users**.

## Discord

**Admin → Integrations → Discord**:

| Setting | Use |
|---|---|
| Client ID, client secret | Lets players link their Discord account under **Account**. Create an application at the Discord Developer Portal and add the redirect `https://your-domain/account/discord/callback`. Only the `identify` scope is used; Discord linking never signs anyone in. |
| Webhook URL | A Discord channel webhook for notifications |
| Notify orders, notify tickets | Which events are posted to that channel |

The Discord invite button comes from **Settings → Discord invite**.

## Notifications

Players see paid orders, received gifts, answered tickets and application decisions under
**Account → Notifications**. Important messages (verification, password reset, order copies) are also sent
by e-mail once [mail is configured](First-Steps.md#2-configure-e-mail).
