# Production acceptance

Record the source revision, target environment, operator, date and evidence for
each gate. A local test pass is evidence for that environment only. Keep payment
and fulfillment disabled until all applicable gates pass. Protect test reports,
customer information and credentials; never attach production environment files.

## Source, assets and distribution

- [ ] Review the exact revision and dependency lock; preserve Laravel/vendor,
  font and game plugin licenses and corresponding-source obligations.
- [ ] Build from a clean checkout with `python tools/package_source.py`; verify
  the resulting manifest/digests and confirm that real configuration, credentials,
  uploads, databases, private notes and development dependencies are absent.
- [ ] Install production Composer dependencies from the reviewed lock with
  `--no-dev`; retain required upstream notices. Build the optional game plugin
  against its separately pinned APIs; do not ship a JDK or API jars with it.
- [ ] Validate all five themes with real content: navigation, dropdowns, forms,
  keyboard/focus, empty/error states, phone widths, 200% zoom and JavaScript off.
  Confirm uploaded images and licensed original fallbacks both work.

## Target hosting and access

- [ ] Verify actual cPanel PHP web/CLI versions, extensions, MariaDB, storage
  permissions, document root at `public/`, timezone and host resource limits.
- [ ] Complete installer and owner/TOTP setup, then confirm installation is locked;
  protect offline console access and preserve the application key.
- [ ] Exercise guest, customer, staff and owner access on the target environment;
  confirm private account, payment, delivery and staff pages are not cached.
- [ ] Verify HTTPS, trusted proxy configuration, actual Cloudflare zone/cache
  rules and edge behavior on a separate staging domain. Document origin exposure
  and recovery arrangements. Do not change an unrelated live site for testing.
- [ ] Confirm real DNS resolution, Java/Bedrock status probes, rate limits,
  outbound mail, verification/reset messages and delivery of support notifications.

## Payment and order lifecycle

- [ ] Run the full local test suite and PHPStan for the revision being accepted.
- [ ] For every enabled real provider, use its official sandbox credentials and
  webhook delivery: exact currency/amount/environment, duplicate events, invalid
  signatures, cancellation, timeout, retry, late success and refund outcomes.
- [ ] Verify stock/quantity/cart and concurrent checkout behavior with independent
  sessions; reconcile wallet and provider records after each lifecycle scenario.
- [ ] Confirm the development sandbox gateway cannot accept production payments.
  A working adapter is not evidence that a merchant account has been approved.

## Minecraft delivery and identity

- [ ] On an authorized dedicated test server, verify the exact supported Paper,
  Java and authentication mode before installing the agent. Obtain any required
  game/server agreements separately; this checklist does not accept them.
- [ ] Verify signed poll/ACK, current lease receipt, stale receipt rejection,
  real grant commands, reconnect/offline deferral and `/vclink` identity ownership.
- [ ] Crash/restart between intent, actual command, durable outcome and ACK; retain
  history, prevent automatic replay and reconcile `uncertain` outcomes manually.
- [ ] Reclaim a lease, rotate keys, update the plugin, simulate timeouts, refund an
  in-flight purchase and verify actual game state as well as website states.
- [ ] Test compensation ordering and idempotent grant receipts where supported.
  Review unresolved outcomes; no exactly-once execution guarantee is implied.

## Operations and final decision

- [ ] Run the actual host cron and bounded queue worker; verify heartbeat, overlap
  locks, reservation expiry, payment retries and wallet reconciliation.
- [ ] Back up and restore database, persistent files, APP_KEY and matching plugin
  history into a separate environment. Verify record hashes, decryption and game
  grant state. Protect backups in storage and transit; test key recovery.
- [ ] Rehearse maintenance mode, migration, rollback and interrupted deployment
  recovery; inspect application/service logs without exposing sensitive values.
- [ ] Complete the requested independent security review and remediate/retest its
  findings. An incomplete review or local hygiene scan is not a security acceptance.
- [ ] Record all failures, unsupported integrations and remaining risks, obtain
  the operator's release decision, then enable only the accepted integrations.

Repository creation, publication, deployment and CI usage require their own
explicit authorization. Completing this checklist does not perform those actions.
