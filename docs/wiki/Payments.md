# Payments

VibeCraft takes card payments through **Stripe** or **PayTR** and keeps player balances in its own wallet.
A payment only counts after the provider's signed notification (webhook) arrives; returning to the site
from the payment page never marks an order as paid by itself.

> Türkçe: [Ödemeler](Odemeler.md)

Payment secrets are entered under **Admin → Integrations**, which only the owner (or a role with the
*integration secrets* permission) can open. Saved secrets are stored encrypted and are never shown again;
leave a secret field empty to keep the current value.

## Stripe

1. In the Stripe Dashboard create the API keys (use **test mode** first).
2. **Developers → Webhooks → Add endpoint**:
   - URL: `https://your-domain/api/payments/stripe/webhook`
   - Events: `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
     `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`,
     `charge.dispute.created`
3. Copy the endpoint's **signing secret** (`whsec_…`).
4. In **Admin → Integrations** enter the publishable key, the secret key and the webhook signing secret, then
   enable Stripe.

Players pay on Stripe Checkout. A refund made in the Stripe Dashboard (`charge.refunded`) and a dispute
(`charge.dispute.created`) are reflected on the order automatically.

## PayTR

1. In the PayTR merchant panel find your **Merchant ID**, **Merchant Key** and **Merchant Salt**.
2. Set the **notification URL** (Bildirim URL) to `https://your-domain/api/payments/paytr/webhook`.
3. In **Admin → Integrations** enter the three values, keep **PayTR test mode** on for
   your first tests, then enable PayTR.

The payment form opens in PayTR's secure iframe. Instalments are turned off. Switch test mode off only after a
successful test purchase.

## Test without real money

Use the providers' own test modes on your real site: Stripe test keys with Stripe's test cards, PayTR with
test mode on. The built-in **sandbox gateway** is only for local development and is not available on a
production site.

## Wallet top-ups

Players top up their balance under **Account → Top up**, either with a free amount or a
[credit package](Store.md#wallet-and-credit). The top-up is paid like an order and credited once the webhook
confirms it. The balance can then pay for store orders and [automatic renewals](Store.md#timed-ranks-and-automatic-renewal).

## When a notification is late or fails

The provider retries webhooks on its own, and VibeCraft retries verified notifications that could not be
processed every five minutes through cron. An order stays *pending* until its payment is confirmed and is
cancelled automatically after 60 minutes without payment.

If an order is paid at the provider but still pending on the site, check that:

- the webhook URL is exactly as above and uses `https://`,
- the signing secret / merchant salt in **Admin → Integrations** matches the provider,
- cron runs every minute ([Installation](Installation.md#6-add-the-cron-job)),
- a firewall, Cloudflare rule or bot protection does not block the webhook URL
  ([Security](Security.md#cloudflare)).

## Refunds

| Paid with | How to refund |
|---|---|
| Site credit (wallet) | **Admin → Orders** → open the order → refund, with a reason. Needs the *refund* permission. The credit goes back to the player's wallet. |
| Stripe | Refund in the Stripe Dashboard. The `charge.refunded` notification updates the order automatically. |
| PayTR | Refund in the PayTR panel. PayTR sends no refund notification, so the order on the site does not change: reverse the in-game items by hand and add a note to the order. |

A refund recorded on the site:

- runs the product's **Refund** commands on the game server,
- stops automatic renewal of ranks bought in that order,
- writes the reversal to the wallet ledger and the audit log.

## Wallet ledger

Every balance change is a ledger entry with a unique key, so a repeated notification or a double click can
never add or take money twice. A daily job at 04:10 checks that balances match the ledger.
