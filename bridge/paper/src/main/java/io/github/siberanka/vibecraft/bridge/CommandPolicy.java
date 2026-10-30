// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

/** Independent local operator approval for exact command roots, including namespaces. */
public final class CommandPolicy {
    private final Set<String> roots;
    public CommandPolicy(List<String> configured) {
        if (configured.isEmpty() || configured.size()>64) throw new IllegalArgumentException("Configure allowed command roots");
        HashSet<String> validated=new HashSet<>();
        for (String root:configured) {
            if (root == null || !root.matches("[a-z0-9_.-]{1,64}(?::[a-z0-9_.-]{1,64})?"))
                throw new IllegalArgumentException("Invalid allowed command root");
            validated.add(root);
        }
        roots=Set.copyOf(validated);
    }
    public boolean allows(String command) {
        if (command == null || command.isBlank() || command.codePoints().anyMatch(Character::isISOControl)) return false;
        String root=command.strip().split(" +",2)[0].toLowerCase(Locale.ROOT);
        return roots.contains(root);
    }
}
