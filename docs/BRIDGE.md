# VibeCraft bridge protocol v1.1 (development)

Game servers call `/api/bridge/v1/poll`, `/ack` and `/link` using HTTPS POST with a
per-server key ID and encrypted-at-rest shared secret. Do not expose RCON publicly.
An implementation must keep game platform API calls on the server's main thread.

## Authentication

Request headers: X-VC-Key, X-VC-Timestamp (Unix seconds), X-VC-Nonce (16–64
alphanumeric, underscore or hyphen characters), X-VC-Signature (lowercase hexadecimal).

```text
HMAC_SHA256(secret,
  UPPERCASE_METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + SHA256(rawBody))
```

PATH includes `/api/bridge/v1/...` and has no query string. SHA256 and HMAC are lowercase
hexadecimal; sign the exact UTF-8 JSON bytes sent. Timestamps outside the configured window
and duplicate nonces are rejected. Never reuse a nonce for a retry; use a fresh request nonce.
Keys are scoped to one game server and can be rotated from the owner administration interface.

Successful responses carry X-VC-Timestamp and X-VC-Signature:

```text
HMAC_SHA256(secret,
  "response\n" + TIMESTAMP + "\n" + requestNonce + "\n" + SHA256(rawResponseBody))
```

Verify HTTPS, response signatures, age, payload limits and field formats before executing
anything. A malformed or unsigned response must never yield executable commands.

## Poll and acknowledge

Poll body: `{"online":["UUID"],"plugin_version":"1.0","platform":"Paper"}`.
The response contains a bounded list of delivery IDs, per-lease receipt tokens, command strings, player identity,
require_online, lease_seconds and poll_after. Online-only deliveries wait until the player
is present. Polls read queued or expired leases from this server only.

ACK body: `{"results":[{"id":"ULID","lease_token":"64 lowercase hexadecimal characters","status":"delivered","message":null}]}`.
Allowed statuses are delivered, failed, deferred and uncertain. Persist a journal keyed by delivery ID
before sending ACK. Duplicate requests and ACKs must be harmless. A deferred delivery is
not a command execution. Failed deliveries use bounded backoff and eventually require review.
Use `uncertain` when a command may have executed but its outcome cannot be established.
It places the delivery and associated order into manual review without automatic replay.
An exception or negative return after dispatch is not proof that no side effects occurred.

Every lease, including a reclaimed delivery, receives a fresh `lease_token`. Echo that exact
token in its acknowledgement. A stale or administratively invalidated token is not accepted
and must not settle a newer lease. Matching duplicate receipts remain idempotent. The token
is a lease receipt, not a substitute for per-server authentication or the durable job journal.
It is omitted from generic model serialization and must not appear in public diagnostics.

This development contract makes `lease_token` mandatory on the existing v1 endpoints;
older ACK payloads return validation errors. Before upgrading any existing installation,
pause bridge processing, reconcile in-flight jobs, migrate the database and upgrade clients
together. The separate development adapter is in `bridge/paper/`; read its supported
Paper/Java/authentication modes, local command approval and license (VibeCraft License) before building.
No released or live-game accepted agent is implied by this specification.

## Linking

The authenticated website user requests an eight-character, short-lived single-use code.
The bridge receives it through a game command and submits the observed UUID, account name,
edition and, for Bedrock, verified Floodgate XUID. A name entered in a web form is never proof.
The bridge must derive identity from the authenticated game connection, never command arguments.

## Crash and refund handling

Executing arbitrary console commands and committing a journal is not one atomic operation.
Persist intent, execute, and persist the outcome. After a crash between intent and outcome,
quarantine that ID for manual review rather than blindly replaying a possibly completed grant.
Prefer idempotent game-side grants with their own receipt IDs when available.

Refund/expiry compensation can race a previously leased purchase command. Until an end-to-end
ordered compensation/journal scenario is verified, ambiguous leased grants require operator
review. Offline API compilation and simulated execution tests must be followed by actual
game-server acceptance, including grant commands, restarts and compensation state.
