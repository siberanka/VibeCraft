// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.util.concurrent.CompletableFuture;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.function.Supplier;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** The scheduled main-thread task must enter the gate before touching the game. */
public final class ExecutionGate {
    private static final int PENDING=0, RUNNING=1, CANCELLED=2, DONE=3;
    private final AtomicInteger state=new AtomicInteger(PENDING);
    private final CompletableFuture<Outcome> result=new CompletableFuture<>();
    public void run(Supplier<Outcome> command) {
        if (!state.compareAndSet(PENDING,RUNNING)) return;
        Outcome outcome=Outcome.UNCERTAIN;
        try { outcome=command.get(); }
        catch (RuntimeException ex) { /* Dispatch may already have had side effects. */ }
        finally { result.complete(outcome); state.set(DONE); }
    }
    public Outcome await(long timeoutMillis) throws InterruptedException {
        try { return result.get(timeoutMillis,TimeUnit.MILLISECONDS); }
        catch (TimeoutException ex) { return cancelPending() ? Outcome.DEFERRED : Outcome.UNCERTAIN; }
        catch (java.util.concurrent.ExecutionException ex) { return Outcome.UNCERTAIN; }
        catch (InterruptedException ex) { cancelPending(); throw ex; }
    }
    public boolean cancelPending() { return state.compareAndSet(PENDING,CANCELLED); }
}
