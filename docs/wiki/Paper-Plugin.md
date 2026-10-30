# Paper Plugin

**VibeCraft Bridge** connects your Minecraft server to the website. It delivers store purchases, links
player accounts and can share statistics and bans for the leaderboards and punishments pages.

> Türkçe: [Paper Eklentisi](Paper-Eklentisi.md)

## How it works

- The plugin **calls the website** over HTTPS; the website never connects to your game server. No RCON or
  open port is needed.
- Every request is signed with a per-server key. The website only hands out deliveries meant for that
  server.
- Before running a command the plugin writes it to a local journal (`receipts.vcj`) and confirms the result
  to the website afterwards. A delivery that was already run is never run again, even after a crash or a
  lost connection.

## Supported servers

| Setup | Supported |
|---|---|
| Paper 26.3 on Java 25, online mode | Yes |
| Paper behind **Velocity** with modern forwarding (online-mode proxy, forwarding secret) | Yes; install the plugin on each Paper server, not on Velocity |
| Bedrock players through **Floodgate** | Yes; they link with their Xbox account (XUID) |
| Offline mode, legacy BungeeCord forwarding, Folia | No; the plugin refuses to start |

## Install

1. Download `VibeCraftBridge-<version>.jar` from the
   [releases page](https://gitlab.com/siberanka/vibecraft/-/releases) and check it with `SHA256SUMS`.
2. Stop the server, put the jar into `plugins/`, start the server once and stop it again. The plugin creates
   `plugins/VibeCraftBridge/config.yml` and stays **disabled**.
3. On the website open **Admin → Servers**, edit the server and click **Rotate bridge key**. The key ID and
   secret are shown **once**; copy them straight into the config.
4. Edit `config.yml`:

```yaml
enabled: true
site: 'https://your-domain'
key-id: 'the key id from the website'
secret: 'the secret from the website'
allowed-command-roots: ['lp', 'crates']
share-statistics: false
share-punishments: false
```

- `site` is only the HTTPS origin: no path, no trailing slash.
- Protect `config.yml`: never paste the secret into chat, screenshots or support messages.

5. Start the server. The server card in **Admin → Servers** shows the last bridge contact.

## Allowed commands

`allowed-command-roots` lists the first word of every console command the website may run, in lower case.
A product command `lp user {uuid} parent add vip` needs `lp`; `crates key give ...` needs `crates`.

- There is no wildcard. Namespaced forms need their own entry: `minecraft:give` is different from `give`.
- Add only the roots your products really use. A root you allow lets the website run *any* command that
  starts with it, so keep the arguments strict in the product commands.
- A delivery whose command root is not allowed is stopped before anything runs and waits for review in the
  admin panel. Add the root, restart, then retry.

## Linking player accounts

Ranks and many products need a verified Minecraft account.

1. The player opens **Account** on the website and clicks **Generate linking code**.
2. In game they type `/vclink CODE` within 10 minutes.
3. The plugin sends the player's real UUID and name from the authenticated session, so nobody can link
   someone else's account.

Players can unlink an account from the same page. Everyone has the `vibecraft.link` permission by default.

## Statistics and bans

| Option | Effect |
|---|---|
| `share-statistics: true` | Sends playtime, player kills, deaths and mob kills of online players for the **Leaderboards** page |
| `share-punishments: true` | Sends the server's profile ban list for the **Punishments** page |

Both are off by default.

## Delivery rules

- Commands run on the server's main thread, one delivery at a time.
- **Player must be online** commands wait until the player joins.
- If an online player's UUID or name no longer matches the order, the delivery is refused before anything
  runs.
- Prefer UUID-based commands, especially for players who are offline at delivery time.
- Expiry and refund commands use the player's current verified name.

## When a delivery is uncertain

If the server crashed after starting a command, the command threw an error, or it ran too long, the plugin
cannot know whether the item was given. The delivery becomes **uncertain** and is never retried
automatically.

1. Check in game (or in the other plugin's logs) whether the player received it, and look at the order.
2. In **Admin → Deliveries** either **Retry** it or **Cancel** it. Retry is safe: the plugin skips any
   delivery its journal already marked as run, so a retry can never give the item twice.
3. If the journal marked it as run but the player did not get the item, give it as a **new**, separate
   delivery and note why on the order.

Running console commands and writing a file can never be one single step, so no system can promise
exactly-once delivery. Commands that are safe to run twice (for example "set rank" instead of "add 100
coins") give the best results.

## Keep the journal safe

`plugins/VibeCraftBridge/receipts.vcj` is the plugin's memory of what it already delivered.

- Keep it across restarts, updates and key rotations; include it in server backups.
- Never delete it to "unstick" a delivery, and never let two servers share one plugin folder.
- Restoring an old journal on its own can replay newer deliveries. Restore it together with the matching
  website backup.

## Rotating the key

Rotate the key when moving to a new machine or if the secret may have leaked: **Admin → Servers → Rotate
bridge key**, paste the new values into `config.yml`, restart. The old key stops working immediately. Do not
reset the journal.

## More detail

- Plugin README: [bridge/paper/README.md](../../bridge/paper/README.md)
- Wire protocol: [docs/BRIDGE.md](../BRIDGE.md)
- Building the jar yourself: [Development](Development.md#building-the-plugin)
