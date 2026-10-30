# Store

How to sell ranks, keys and items and have them delivered on your server.

> Türkçe: [Mağaza](Magaza.md)

## How a purchase works

1. A player adds products to the cart, reviews the final price and accepts your sales terms.
2. The player pays by card (Stripe or PayTR) or with site credit from their wallet.
3. When the payment provider confirms the payment, the order is paid and a **delivery** is queued.
4. The [Paper plugin](Paper-Plugin.md) on your game server picks up the delivery and runs the product's
   console commands. With chest delivery the item waits in the player's **Chest** until they claim it.

Unpaid orders are cancelled automatically after 60 minutes.

## Prices and currency

All amounts are entered in **minor units**: `2500` means 25.00. The currency is set with
`VIBECRAFT_CURRENCY` in `.env` (default `TRY`). Decide it before the first sale and do not change it later.

## Categories

**Admin → Categories**: name, description, image and sort order (lower numbers first). Every product belongs
to one category.

## Products

**Admin → Products → New**:

| Field | Meaning |
|---|---|
| Category, name, summary, image | What players see in the store |
| Price in minor units | `1500` = 15.00 |
| Stock | Leave blank for unlimited |
| Maximum quantity | Highest quantity per order line |
| Per-account limit | How many times one account may buy it (blank = no limit) |
| Duration in days | Fill in for timed products such as monthly ranks; leave blank for permanent ones |
| Verified Minecraft account required | The buyer must have linked their game account first (recommended for ranks) |
| Deliver through the chest | The player claims the item later from `/chest` instead of immediate delivery |
| Visible | Hidden products cannot be bought |

## Product commands

A product does nothing in game until you attach commands. **Admin → Product commands → New**:

| Field | Meaning |
|---|---|
| Product, server | Which product, and on which game server the command runs |
| Phase | **Purchase** runs after payment, **Expiry** when a timed product ends, **Refund** when an order is refunded |
| Console command | Without the leading `/` |
| Player must be online | Waits until the player is online before running |
| Sort order | Order of several commands of the same phase |

Placeholders:

| Placeholder | Replaced with |
|---|---|
| `{player}` | The player's verified Minecraft name |
| `{uuid}` | The player's UUID with dashes |
| `{uuid_nodash}` | The UUID without dashes |
| `{quantity}` | Quantity bought |
| `{order}` | The order reference |
| `{days}` | The product duration in days |

Only these typed values can reach a command; product names, notes or anything else players type never can.
Examples:

```
lp user {uuid} parent addtemp vip {days}d
crates key give {player} legendary {quantity}
lp user {uuid} parent remove vip
```

Prefer UUID-based commands. Each command's first word (the "root", for example `lp` or `crates`) must also
be allowed in the plugin's `allowed-command-roots`, otherwise the delivery is stopped for review. See
[Paper Plugin](Paper-Plugin.md#allowed-commands).

## Timed ranks and automatic renewal

A product with **Duration in days** creates a timed entitlement. When it ends, the **Expiry** commands run.

Players can turn on **automatic renewal** for an active timed rank from their account. When the rank reaches
its end date, before the expiry runs, the price is charged from their wallet and the rank is extended; no
purchase command is sent again.
If the balance is too low, renewal switches off and the normal expiry runs. Renewals of suspended accounts
stop instead of charging them.

## Wallet and credit

Players keep a balance in your site currency (the name is set by **Balance name** in Settings).

- **Credit packages** (**Admin → Credit packages**): a price and an optional **bonus** that is added on top,
  for example pay 100.00 and receive 110.00 credit.
- **Credit codes** (**Admin → Credit codes**): generate codes worth a fixed amount, with a count, an optional
  maximum number of redemptions, an expiry date and a private note. Players redeem them under
  **Account → Redeem a code**.
- Staff with the *wallet adjustment* permission can correct a balance; every change is written to the ledger
  and the audit log.

## Coupons

**Admin → Coupons**:

| Field | Meaning |
|---|---|
| Code | Upper-case letters, numbers, `_` or `-` |
| Type | **Percentage** (`100` = 1%, so `1000` = 10%) or **Fixed** (minor units) |
| Minimum subtotal | Cart total needed before the coupon applies |
| Maximum uses, uses per account | Overall and per-player limits |
| Starts at, ends at, active | Validity window |

## Gifts

With **Players can send gifts** enabled in Settings a player can enter another player's site user name at checkout. The
recipient must be an active account with a linked Minecraft account; the delivery goes to that player, while
the buyer pays and keeps the order.

## Orders and deliveries

- **Admin → Orders**: every order with payment and delivery status. Staff can cancel unpaid orders and,
  with the finance permission, refund orders paid with site credit. Card refunds are made at the payment
  provider; see [Payments](Payments.md#refunds). A refund runs the product's **Refund** commands.
- **Admin → Deliveries**: the delivery queue. A delivery the plugin could not confirm becomes
  **uncertain** and waits for a person to check the game server and decide; see
  [Paper Plugin](Paper-Plugin.md#when-a-delivery-is-uncertain).

Each order keeps a copy of the product, price and accepted documents at the time of purchase, so later
edits never change what a player bought. Players can download that copy from their order page.

## Store options in Settings

- **Show recent purchases**: a home page list of the latest purchases (player name, product and time; no prices).
- **Gifts**: allow buying for another player.
- **Balance name**: the name of the site currency.
