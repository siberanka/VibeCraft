// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.util.UUID;

/** Decides whether player identities on this server are authenticated and which edition a UUID belongs to. */
public final class Topology {
    private Topology() {}

    /**
     * Online-mode Paper, or Paper behind Velocity modern forwarding with an online-mode proxy and a
     * configured forwarding secret. Legacy BungeeCord IP forwarding is refused because a client that
     * reaches the backend directly can forge it.
     */
    public static boolean authenticated(boolean onlineMode, boolean velocityEnabled, boolean velocityOnlineMode, String velocitySecret) {
        return onlineMode || (velocityEnabled && velocityOnlineMode && velocitySecret != null && velocitySecret.strip().length() >= 12);
    }

    /** Floodgate assigns Bedrock players the UUID (0, XUID); online Java UUIDs are never in that range. */
    public static boolean bedrock(UUID uuid) {
        return uuid.getMostSignificantBits() == 0L && uuid.getLeastSignificantBits() > 0L;
    }

    public static String xuid(UUID uuid) {
        return bedrock(uuid) ? Long.toString(uuid.getLeastSignificantBits()) : null;
    }
}
