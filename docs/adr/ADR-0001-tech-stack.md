# ADR-0001 — Technology stack

- **Status:** Accepted — 2026-10-24
- **Supersedes:** preliminary stack notes in the product specification (§7.1)

## Context

VibeCraft must run first-class on an ordinary cPanel shared-hosting account: PHP-FPM/LSAPI,
MySQL/MariaDB, cron, FTP/File Manager, outbound HTTPS — no SSH, no root, no long-running
process, no Node.js runtime, no Redis. Payment, ledger and delivery code must be transactional and
idempotent. The codebase has to stay maintainable for at least three years.

## Candidates measured (2026-10-24, Windows 11, PHP 8.4.27 NTS, opcache off, 128 MB memory limit)

| Candidate | Version | Prod `vendor/` files | Prod `vendor/` size | Packages | Cold request (no opcache) | Peak memory |
|---|---|---:|---:|---:|---:|---:|
| A — Laravel + Blade | 13.38.0 (PHP ≥ 8.3) | 6 564 | 42 MB | 99 | ~185 ms (welcome view, DB session) | 19.5 MB |
| B — Symfony LTS + Twig + Doctrine | 7.4.21 (PHP ≥ 8.2) | 5 257 | 36 MB | 100 | ~16 ms (empty skeleton, 404 route) | 4.1 MB |

The Symfony number renders no view and opens no session, so the request figures are **not** an
equal-work comparison; they only prove that both stay far below a 128 MB limit. Footprints are
comparable once Symfony carries equivalent components (security, ORM, messenger, scheduler,
mailer, rate limiter, forms, validator).

Rejected outright (hard gates of the specification): Node SSR (requires a resident Node
process), Go/Java backends (require a daemon / JVM), WordPress bridge (critical logic delegated
to third-party plugins), a self-written micro framework (re-implements auth, CSRF, sessions).

## Decision

**PHP ≥ 8.4 + Laravel 13 + server-rendered Blade + dependency-free vanilla JavaScript.**

Reasons that decided between A and B:

1. **Shared-hosting operations built in:** database queue + `schedule:run` from a single cron entry,
   `queue:work --stop-when-empty`, file/database cache, database sessions — all without a daemon.
2. **Security building blocks maintained upstream:** Fortify (login throttling, password reset,
   TOTP 2FA with encrypted secrets and recovery codes, password confirmation), encrypted casts,
   signed URLs, rate limiter, CSRF and maintained password hashing.
3. **Theming:** Blade view namespaces make five themes with shared components cheap; Twig would
   work too but adds no capability we need.
4. **Ecosystem fit for a Minecraft web product** (payment/OAuth/HTTP clients, Larastan, Pint).

PHP 8.4 is the minimum (active support until 2026-12-31, security until 2028-12-31);
PHP 8.5 is tested as well. PHP 8.2 / 8.3 are not supported by VibeCraft even though Laravel 13
accepts 8.3.

## Consequences

- Laravel 13 receives security fixes until 2028-03-17 → a framework major upgrade is planned
  before that date (expand → migrate → contract).
- No asset build step: CSS and ES modules are hand-written and self-hosted; nothing is fetched
  from third-party CDNs at runtime (supports a strict nonce-based CSP).
- Release ZIP ships `vendor/` (no Composer on the host).
- Reversal cost: domain services are framework-light (plain PHP classes in `app/Domain`), so a
  move to Symfony would mainly touch controllers, views and persistence.

## Not done in this ADR (recorded honestly)

The specification asked for three dual-stack PoCs (auth, payment, deployment). Only the footprint
and boot measurement above was run on both stacks; the auth/payment/deployment PoCs were executed
on the chosen stack as the first real vertical slice and are covered by the test suite.
