# VibeCraft Paper Bridge (development)

Original bridge for the development v1.1 website contract. Targets **Paper
26.3.build.226-stable and Java 25**. Runs on online-mode Paper, or on Paper behind Velocity modern forwarding with an
online-mode proxy and a forwarding secret. Bedrock players joining through Floodgate
link as Bedrock identities with their XUID. Offline mode, legacy BungeeCord forwarding
and Folia are refused at startup. API compilation and core/transport tests do not establish
live Minecraft acceptance. Do not enable real-money delivery until the complete
website and game-side acceptance matrix passes.

## Build without CI or automatic dependency downloads

Use a trusted JDK 25 and a POSIX shell with `sha256sum`, `find`, `sort`, `cp`.
Place the six exact provided jars listed in `dependencies.sha256` in a separate
directory. The Paper artifact comes from:

https://repo.papermc.io/repository/maven-public/io/papermc/paper/paper-api/26.3.build.226-stable/paper-api-26.3.build.226-stable.jar

The remaining artifacts are published at Maven Central under their standard
coordinates: `com.google.code.gson:gson:2.14.0`, `net.kyori:adventure-api:5.2.0`,
`net.kyori:adventure-key:5.2.0`, `org.jspecify:jspecify:1.0.0`,
`org.jetbrains:annotations:26.0.2`. Check their upstream licenses before
redistributing dependency files. The build checks every pinned SHA-256, compiles
against the actual APIs with annotation processing disabled, runs the core
regressions and creates a jar containing only this plugin's classes and resources.

```sh
sh build.sh /absolute/path/to/provided-api-jars
```

Output: `target/VibeCraftBridge-0.9.0-beta.jar`. Use a clean checkout for a release
build. Stable archive timestamps require the same JDK/compiler for byte-for-byte
reproducibility. No Gradle/Maven wrapper, CI workflow or downloaded build script
runs automatically.

## Optional offline lifecycle tests

The separate `src/integration` suite loads the packaged final plugin with
MockBukkit 4.118.0. Its simulated runtime uses Paper API **26.3.build.200-stable**;
the production build above still compiles against build226. The exact 57 test-only
jars, coordinates, download URLs and SHA-256 pins are in
`integration-dependencies.json` and `integration-dependencies.sha256`. Obtain and
review them separately; the script performs no dependency downloads. These jars
are never embedded in the plugin or required by its production build.

Inside a disposable Linux VM/container with no external network, JDK25, a writable
`/scratch/qa` mount and a clean production build:

```sh
sh test-lifecycle.sh /absolute/path/to/mock-runtime-jars /scratch/qa/lifecycle-fixture
```

The suite creates a loopback-only TLS fixture using a temporary local certificate
and a matching JSSE truststore, checks signed requests, loads/enables/disables the
actual jar, ticks the mock main-thread scheduler, dispatches a registered test
command, retries a lost acknowledgement after re-enabling, links an authenticated
mock player and checks disconnect/name-change behavior. Its permissions are taken
from the actual manifest; the helper registers them because MockBukkit's jar loader
omits that Paper loader step. No Minecraft implementation/world, external site,
EULA acceptance or real credentials are involved. Passing this simulation does
not close live Paper, plugin interoperability or real-money acceptance gates.

## Configuration and operation

1. Stop the test server, install the jar in `plugins/`, start once to create the
   disabled configuration, then stop again.
2. In the website owner panel, enable the matching game server bridge and create
   per-server credentials. Set `site` to the HTTPS **origin** (no subdirectory,
   credentials, query or fragment), `key-id`, `secret`, and an explicit list of
   `allowed-command-roots`, then `enabled: true`. Namespaced variants such as
   `minecraft:give` need their own entry; no wildcard is supported. Approve only
   commands needed by reviewed products and restrict their arguments in the website
   templates. Local root approval does not validate every plugin-specific argument.
   Use separate staging credentials. Protect `config.yml` and backups from other
   users; never paste keys into logs, screenshots or support messages.
3. Restart Paper. Keep outbound HTTPS available; no inbound RCON port is needed.
   The runtime uses Java's normal certificate/hostname checks and refuses redirects.
4. Players request a single-use code on the website and run `/vclink CODE` in
   their authenticated game session. UUID/name come from that session.
5. Monitor both delivery states and bridge heartbeat in the website owner panel.
   Polling and HTTPS run on one worker; game API calls run on Paper's main thread.
   If an online player's UUID/name no longer matches the delivery snapshot, or a command
   root is not approved locally, that delivery is refused before anything runs and is
   reported to the website as failed; other deliveries keep flowing. Refused deliveries
   are retried and then wait for review in the owner panel. Prefer UUID-based grant
   commands, especially for offline delivery: an offline name cannot be revalidated from
   a live connection. Expiry and refund commands use the player's current verified name.

The plugin writes `receipts.vcj` with a site binding, stable delivery ID, command
fingerprint and outcome. It holds an exclusive lock, checks bounded records/CRC,
forces writes before dispatch and before acknowledgement, and refuses damaged or
full history. Commands, shared secrets and rotating lease receipts are not stored
in that history. Preserve it across restarts, updates and key rotation. Never run
two agents sharing one data directory or delete history to clear a delivery.

An interrupted intent, exception/false return after dispatch, or running-command
timeout becomes `uncertain`, requiring operator review. A command provably not
started can be deferred and retried. Previously delivered IDs are acknowledged
with their current lease receipt without execution. Reused IDs with changed
content stop processing. An expired receipt is never permission to run again.
An unapproved command root also pauses delivery before dispatch; update the local
policy only after reviewing the product template, then restart the agent.

Arbitrary console execution and durable storage are not atomic: this is **not an
exactly-once guarantee**. Use idempotent game-side grant commands where possible.
Stop both bridge and fulfillment workers before moving a site or restoring a
backup; reconcile website and plugin history together, including expired leases,
refunds and external game state. Do not rotate the journal when rotating keys.

On `uncertain` or journal failure: pause delivery, inspect the actual game-side
grant and website order, record the operator decision, then reconcile the website.
An old delivered journal entry cannot be overridden by merely pressing Retry.
Issue any verified replacement as a new, separately recorded delivery. Back up
the website database, persistent files, APP_KEY and matching plugin history in
protected storage. Restoring only an older journal can replay newer grants.

VibeCraft License (PolyForm Noncommercial License 1.0.0 with additional terms), the same as the
website: run it on your own servers, including commercial ones, but do not sell it or a renamed copy.
See LICENSE and NOTICE.md for the terms, authors and compile-only dependencies.
