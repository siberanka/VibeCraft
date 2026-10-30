# VibeCraft Paper Bridge

Required Notice: Copyright (c) 2026 siberanka (https://github.com/siberanka/VibeCraft)

This plugin is part of VibeCraft and is licensed under the VibeCraft License in LICENSE (PolyForm
Noncommercial License 1.0.0 with additional terms): it may be built, changed and run on servers you run,
including commercial ones, but it may not be sold, rented or resold under any name. No Minecraft server
implementation, game assets or third-party bytecode is embedded in this plugin jar.

The compile-only Paper API is a work of the Bukkit, Spigot and Paper contributors.
Paper inherits GPL version 3 licensing, with selected contributors' work available
under MIT. Preserve the upstream authors and their notices when redistributing
upstream artifacts: https://github.com/PaperMC/Paper/blob/main/LICENSE.md .

Provided by the target Paper installation, not bundled here:

| Dependency | Upstream authors | License | Project |
| --- | --- | --- | --- |
| Paper API 26.3.build.226-stable | Bukkit, Spigot and Paper contributors | GPL-3.0 / upstream MIT portions | https://papermc.io/ |
| Gson 2.14.0 | Google Inc. and contributors | Apache-2.0 | https://github.com/google/gson |
| Adventure API / Key 5.2.0 | KyoriPowered contributors | MIT | https://github.com/KyoriPowered/adventure |
| JSpecify 1.0.0 annotations | The JSpecify Authors | Apache-2.0 | https://github.com/jspecify/jspecify |
| JetBrains annotations 26.0.2 | JetBrains s.r.o. | Apache-2.0 | https://github.com/JetBrains/java-annotations |

When distributing this plugin's binary free of charge, include LICENSE and the NOTICE files. Do not
include API jars or a JDK in the plugin distribution; their redistribution has separate obligations.

Names identify compatibility and authorship; they do not imply endorsement.

Optional integration tests reference **MockBukkit4.118.0**, an MIT-licensed project
by Sebastiaan de Schaetzen, TheBusyBiscuit, Pierce Thompson, Eve Kolb, Hjalmar
Gunnarsson, Afonso Oliveira and other MockBukkit contributors:
https://github.com/MockBukkit/MockBukkit . No MockBukkit source or bytecode is copied
into this plugin. Its test-only transitive artifacts, including Paper API build200,
are pinned separately in integration-dependencies.json. Their upstream licenses
remain applicable if those artifacts are redistributed; the source distribution
contains coordinates and checksums, not the dependency jars or a JDK.
