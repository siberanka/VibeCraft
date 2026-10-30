// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.io.IOException;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** Called by one worker. Durable history outlives both HTTP receipts and key rotation. */
public final class DeliveryProcessor {
    public interface Executor {
        /** False is permitted only when no game command has started. */
        boolean ready(Protocol.Job job) throws Exception;
        /** Any failure after dispatch, including false, is an uncertain execution. */
        Outcome execute(Protocol.Job job) throws Exception;
    }
    private final Journal journal;
    private final Executor executor;
    public DeliveryProcessor(Journal journal, Executor executor) { this.journal=journal; this.executor=executor; }
    public synchronized Outcome process(Protocol.Job job) throws IOException, InterruptedException {
        Outcome previous=journal.previous(job);
        if (previous != null && previous != Outcome.DEFERRED) return previous;
        try { if (!executor.ready(job)) return Outcome.DEFERRED; }
        catch (InterruptedException ex) { throw ex; }
        // A refused precheck concerns this delivery only: nothing ran, so report it and keep serving others.
        catch (Exception ex) { return Outcome.REJECTED; }
        journal.intent(job);
        Outcome outcome;
        try { outcome=executor.execute(job); }
        catch (InterruptedException ex) {
            // The main-thread task may already be running. Leave INTENT durable.
            Thread.currentThread().interrupt(); throw ex;
        } catch (Exception ex) { outcome=Outcome.UNCERTAIN; }
        if (outcome != Outcome.DELIVERED && outcome != Outcome.UNCERTAIN && outcome != Outcome.DEFERRED)
            outcome=Outcome.UNCERTAIN;
        journal.complete(job,outcome);
        return outcome;
    }
}
