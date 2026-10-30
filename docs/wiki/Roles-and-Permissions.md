# Roles and Permissions

Every account has exactly one role. Staff roles open the admin panel; what each person can do there depends on
the permissions of the role.

> Türkçe: [Roller ve Yetkiler](Roller-ve-Yetkiler.md)

## Built-in roles

| Role | For | Can |
|---|---|---|
| **owner** | You and trusted co-owners | Everything, including roles, payment secrets and wallet corrections |
| **admin** | Senior staff | Store, coupons, orders and refunds, deliveries, servers, content, support, settings, appearance, users and bans, forum, applications, audit log, system health |
| **moderator** | Moderators | Users and bans, content, support, forum moderation, staff applications |
| **support** | Support team | Users, orders (read only) and support tickets |
| **member** | Players | No admin access |

Only an **owner** can change roles: **Admin → Users** → open the user → **Account role**. The last owner can
never be removed, so you cannot lock yourself out by mistake.

## Permission list

| Permission | Allows |
|---|---|
| `admin.access` | Opening the admin panel |
| `users.view` / `users.manage` / `users.ban` | Seeing accounts / editing them and awarding badges / banning and unbanning |
| `roles.manage` | Changing roles (owner only) |
| `store.manage` | Categories, products, product commands, credit packages |
| `coupons.manage` | Coupons |
| `orders.view` / `orders.manage` | Seeing orders / cancelling them and adding notes |
| `finance.refund` | Refunding orders paid with site credit |
| `finance.wallet_adjust` | Correcting a player's balance (owner only) |
| `deliveries.manage` | Retrying or cancelling deliveries |
| `servers.manage` | Game servers and bridge keys |
| `content.manage` | News, pages, wiki, vote sites, media library |
| `tickets.view` / `tickets.reply` | Reading / answering support tickets |
| `settings.manage` | Site settings and legal documents |
| `appearance.manage` | Themes, colours, menus, custom CSS |
| `integrations.manage` | Payment, CAPTCHA and Discord secrets (owner only) |
| `forum.moderate` | Forum categories and moderation |
| `applications.review` | Staff positions and applications |
| `audit.view` | The audit log |
| `system.view` | System health information |

## Staff security

- **Two-factor authentication** is required for every staff role before the admin panel opens. Keep it on
  (**Settings → Require two-factor authentication for staff**).
- The admin panel asks for the password again before sensitive areas, at most every few hours.
- Every change in the admin panel is written to the **audit log** with the staff member, time and the
  changed fields (never secret values).
- Owner role changes and bans are protected against removing the last owner.

## Lost owner access

If the only owner loses access, a person with File Manager and cron (or SSH) access can restore it:

```
php artisan vibecraft:grant-owner owner@example.com
```

This gives the owner role to that existing account. It does not turn off two-factor authentication; use the
account's recovery codes for that. Protect your hosting login: anyone with console access can run this
command.

## Bans

Staff with `users.ban` ban an account on the user's page with a reason. A banned account cannot sign in, its
automatic renewals stop, and it cannot receive gifts. Game bans are separate; they come from your server and
show on the punishments page.
