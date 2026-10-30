// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.io.IOException;
import java.net.URI;
import java.net.http.HttpHeaders;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicReference;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** No server, outbound network or real credentials; tests crash boundaries and wire fixtures. */
public final class CoreTests {
    private static final String SITE="https://example.test/";
    private static int assertions;
    private CoreTests() {}
    @FunctionalInterface private interface Checked { void run() throws Exception; }
    private static void check(boolean value, String message) {
        assertions++; if (!value) throw new AssertionError(message);
    }
    private static void refuses(Checked task, String message) throws Exception {
        boolean refused=false;
        try { task.run(); } catch (IOException | IllegalArgumentException ex) { refused=true; }
        check(refused,message);
    }
    private static Protocol.Job job(String receipt) {
        return new Protocol.Job("01j"+"0".repeat(23),receipt,"fixture grant BuilderQA",
                UUID.fromString("12345678-1234-4234-8234-123456789abc"),"BuilderQA",true);
    }
    private static final class Game implements DeliveryProcessor.Executor {
        int calls;
        boolean online=true;
        boolean throwsAfterDispatch;
        boolean refusesPrecheck;
        Outcome result=Outcome.DELIVERED;
        @Override public boolean ready(Protocol.Job value) {
            if (refusesPrecheck) throw new IllegalStateException("Synthetic identity mismatch");
            return online;
        }
        @Override public Outcome execute(Protocol.Job value) throws IOException {
            calls++; if (throwsAfterDispatch) throw new IOException("Synthetic partial execution"); return result;
        }
    }
    public static void main(String[] ignored) throws Exception {
        Path root=Files.createTempDirectory("vc-core-");
        try {
            journalScenarios(root); gateScenarios(); protocolScenarios(); policyScenarios(); topologyScenarios();
            System.out.println("BRIDGE_CORE_PASS assertions="+assertions+" durable_history=verified execution_gate=verified signed_wire=verified");
        } finally {
            try (var paths=Files.walk(root)) {
                for (Path path:paths.sorted(java.util.Comparator.reverseOrder()).toList()) Files.delete(path);
            }
        }
    }
    private static void journalScenarios(Path root) throws Exception {
        Protocol.Job initial=job("a".repeat(64)), reclaimed=job("b".repeat(64));
        Game game=new Game(); Path path=root.resolve("delivered.vcj");
        try (Journal journal=new Journal(path,SITE)) {
            DeliveryProcessor processor=new DeliveryProcessor(journal,game);
            check(processor.process(initial)==Outcome.DELIVERED,"Initial delivery");
            check(processor.process(initial)==Outcome.DELIVERED && game.calls==1,"Duplicate ACK does not replay");
            check(processor.process(reclaimed)==Outcome.DELIVERED && game.calls==1,"Reclaim changes receipt only");
            refuses(() -> { try (Journal second=new Journal(path,SITE)) { second.previous(initial); } },"Exclusive journal lock");
            Protocol.Job changed=new Protocol.Job(initial.id(),initial.receipt(),"fixture changed",initial.player(),initial.name(),true);
            refuses(() -> processor.process(changed),"Changed payload quarantined");
        }
        try (Journal journal=new Journal(path,SITE)) {
            check(new DeliveryProcessor(journal,game).process(reclaimed)==Outcome.DELIVERED && game.calls==1,"Restart does not replay");
        }
        refuses(() -> { try (Journal other=new Journal(path,"https://other.example.test/")) { other.previous(initial); } },"Site binding retained");
        String persisted=new String(Files.readAllBytes(path),StandardCharsets.ISO_8859_1);
        check(!persisted.contains(initial.command()) && !persisted.contains(initial.receipt()),"History omits command and receipt");
        check(!initial.toString().contains(initial.command()) && !initial.toString().contains(initial.receipt()),"Delivery diagnostics redacted");

        Path crash=root.resolve("crash.vcj"); Game crashing=new Game();
        try (Journal journal=new Journal(crash,SITE)) {
            journal.intent(initial);
            crashing.execute(initial); // Simulated game side effect, crash before durable outcome.
        }
        try (Journal journal=new Journal(crash,SITE)) {
            check(new DeliveryProcessor(journal,crashing).process(reclaimed)==Outcome.UNCERTAIN && crashing.calls==1,"Crash after dispatch before outcome never replays");
        }
        Path partial=root.resolve("partial.vcj"); Game failing=new Game(); failing.throwsAfterDispatch=true;
        try (Journal journal=new Journal(partial,SITE)) {
            DeliveryProcessor processor=new DeliveryProcessor(journal,failing);
            check(processor.process(initial)==Outcome.UNCERTAIN,"Partial execution needs review");
            check(processor.process(reclaimed)==Outcome.UNCERTAIN && failing.calls==1,"Uncertain outcome is durable");
        }
        Path deferred=root.resolve("deferred.vcj"); Game offline=new Game(); offline.online=false;
        try (Journal journal=new Journal(deferred,SITE)) {
            DeliveryProcessor processor=new DeliveryProcessor(journal,offline);
            check(processor.process(initial)==Outcome.DEFERRED && offline.calls==0,"Offline precheck never dispatches");
            offline.online=true; offline.result=Outcome.DEFERRED;
            check(processor.process(initial)==Outcome.DEFERRED && offline.calls==1,"Definite cancellation stored");
        }
        Path rejected=root.resolve("rejected.vcj"); Game refusing=new Game(); refusing.refusesPrecheck=true;
        try (Journal journal=new Journal(rejected,SITE)) {
            DeliveryProcessor processor=new DeliveryProcessor(journal,refusing);
            check(processor.process(initial)==Outcome.REJECTED && refusing.calls==0,"Refused precheck is reported per delivery");
            check(journal.previous(initial)==null,"Refused precheck writes no execution intent");
            check(new String(Protocol.ack(initial,Outcome.REJECTED),java.nio.charset.StandardCharsets.UTF_8).contains("\"status\":\"failed\""),"Refusal is acknowledged as failed");
        }
        try (Journal journal=new Journal(deferred,SITE)) {
            offline.result=Outcome.DELIVERED;
            check(new DeliveryProcessor(journal,offline).process(reclaimed)==Outcome.DELIVERED && offline.calls==2,"Safe deferred command can retry");
        }
        try (Journal journal=new Journal(deferred,SITE)) { check(journal.previous(reclaimed)==Outcome.DELIVERED,"Deferred transition replay valid"); }

        byte[] bytes=Files.readAllBytes(path);
        Path torn=root.resolve("torn.vcj"); Files.write(torn,java.util.Arrays.copyOf(bytes,bytes.length-1));
        refuses(() -> { try (Journal journal=new Journal(torn,SITE)) { journal.previous(initial); } },"Torn journal refuses startup");
        bytes[40]^=1; Path corrupt=root.resolve("corrupt.vcj"); Files.write(corrupt,bytes);
        refuses(() -> { try (Journal journal=new Journal(corrupt,SITE)) { journal.previous(initial); } },"CRC damage refuses startup");
        Path link=root.resolve("linked.vcj"); Files.createSymbolicLink(link,path);
        refuses(() -> { try (Journal journal=new Journal(link,SITE)) { journal.previous(initial); } },"Journal symlink refused");
        Path closed=root.resolve("closed.vcj"); Game idle=new Game(); Journal unavailable=new Journal(closed,SITE); unavailable.close();
        refuses(() -> new DeliveryProcessor(unavailable,idle).process(initial),"Unavailable journal stops execution");
        check(idle.calls==0,"No command without durable history");
    }
    private static void gateScenarios() throws Exception {
        AtomicInteger calls=new AtomicInteger();
        ExecutionGate pending=new ExecutionGate();
        check(pending.await(1)==Outcome.DEFERRED,"Pending timeout is safe cancellation");
        pending.run(() -> { calls.incrementAndGet(); return Outcome.DELIVERED; });
        check(calls.get()==0,"Late scheduled task cannot run after cancellation");
        ExecutionGate running=new ExecutionGate(); CountDownLatch entered=new CountDownLatch(1), release=new CountDownLatch(1);
        Thread thread=new Thread(() -> running.run(() -> {
            entered.countDown();
            try { release.await(); } catch (InterruptedException ex) { Thread.currentThread().interrupt(); }
            calls.incrementAndGet(); return Outcome.DELIVERED;
        }));
        thread.start(); check(entered.await(2,java.util.concurrent.TimeUnit.SECONDS),"Main-thread fixture entered");
        check(running.await(1)==Outcome.UNCERTAIN,"Running timeout cannot claim nonexecution");
        release.countDown(); thread.join(2000);
        check(!thread.isAlive() && calls.get()==1,"Running task completes without interruption or replay");
        ExecutionGate error=new ExecutionGate(); error.run(() -> { throw new IllegalStateException("Synthetic dispatch failure"); });
        check(error.await(10)==Outcome.UNCERTAIN,"Dispatch exception needs review");
        ExecutionGate interrupted=new ExecutionGate(); AtomicReference<Throwable> failure=new AtomicReference<>();
        Thread waiter=new Thread(() -> { try { interrupted.await(5000); } catch (InterruptedException ex) { failure.set(ex); } });
        waiter.start(); waiter.interrupt(); waiter.join(2000);
        interrupted.run(() -> { calls.incrementAndGet(); return Outcome.DELIVERED; });
        check(failure.get() instanceof InterruptedException && calls.get()==1,"Interrupted pending task never dispatches");
    }
    private static void protocolScenarios() throws Exception {
        check(Protocol.sign("\u000b".repeat(20),"Hi There").equals("b0344c61d8db38535ca8afceaf0bf12b881dc200c9833da726e9376c2e32cff7"),"RFC4231 HMAC vector");
        String secret="7".repeat(64), nonce="fixture_nonce_0123456789";
        byte[] body="{\"deliveries\":[],\"lease_seconds\":120,\"poll_after\":15}".getBytes(StandardCharsets.UTF_8);
        String signature=Protocol.sign(secret,"response\n1000\n"+nonce+"\n"+Protocol.hex(Protocol.hash(body)));
        HttpHeaders headers=HttpHeaders.of(Map.of("X-VC-Timestamp",List.of("1000"),"X-VC-Signature",List.of(signature)),(a,b)->true);
        SignedClient.verify(secret,nonce,headers,body,1000); check(true,"Valid response signature");
        refuses(() -> SignedClient.verify(secret,"other_nonce_0123456789",headers,body,1000),"Nonce bound response");
        refuses(() -> SignedClient.verify(secret,nonce,headers,"{}".getBytes(StandardCharsets.UTF_8),1000),"Body bound response");
        refuses(() -> SignedClient.verify(secret,nonce,headers,body,1201),"Old response rejected");
        HttpHeaders duplicate=HttpHeaders.of(Map.of("X-VC-Timestamp",List.of("1000","1000"),"X-VC-Signature",List.of(signature)),(a,b)->true);
        refuses(() -> SignedClient.verify(secret,nonce,duplicate,body,1000),"Duplicate signing headers rejected");
        check(Protocol.batch(body).jobs().isEmpty(),"Empty poll contract");
        com.google.gson.JsonObject entry=new com.google.gson.JsonObject(); Protocol.Job initial=job("a".repeat(64));
        check(initial.matchesObserved(initial.player(),"builderqa"),"Observed UUID and case-insensitive account name agree");
        check(!initial.matchesObserved(initial.player(),"RenamedQA"),"Renamed online player cannot use a stale name snapshot");
        check(!initial.matchesObserved(UUID.fromString("22345678-1234-4234-8234-123456789abc"),initial.name()),"Matching name cannot substitute for the observed UUID");
        entry.addProperty("id",initial.id()); entry.addProperty("lease_token",initial.receipt()); entry.addProperty("command",initial.command());
        entry.addProperty("player_uuid",initial.player().toString()); entry.addProperty("player_name",initial.name()); entry.addProperty("require_online",true);
        com.google.gson.JsonObject root=Protocol.object(body); com.google.gson.JsonArray list=root.getAsJsonArray("deliveries"); list.add(entry);
        check(Protocol.batch(root.toString().getBytes(StandardCharsets.UTF_8)).jobs().size()==1,"Real-shaped delivery accepted");
        list.add(entry.deepCopy());
        refuses(() -> Protocol.batch(root.toString().getBytes(StandardCharsets.UTF_8)),"Duplicate jobs invalidate whole batch");
        list.remove(1); entry.addProperty("require_online","true");
        refuses(() -> Protocol.batch(root.toString().getBytes(StandardCharsets.UTF_8)),"String boolean invalidates batch");
        refuses(() -> Protocol.object("{} {}".getBytes(StandardCharsets.UTF_8)),"Trailing JSON rejected");
        refuses(() -> Protocol.object("{unquoted:true}".getBytes(StandardCharsets.UTF_8)),"Non-strict JSON rejected");
        refuses(() -> Protocol.object(new byte[Protocol.MAX_BODY+1]),"Body length capped");
        refuses(() -> Protocol.object(new byte[]{'{','"','x','"',':','"',(byte)0xc3,(byte)0x28,'"','}'}),"Malformed UTF-8 rejected");
        String ack=new String(Protocol.ack(initial,Outcome.UNCERTAIN),StandardCharsets.UTF_8);
        check(ack.contains("\"status\":\"uncertain\"") && ack.contains(initial.receipt()),"ACK echoes current receipt and outcome");
        refuses(() -> Protocol.ack(initial,Outcome.INTENT),"Intent cannot be acknowledged as success");
        for (String bad:List.of("http://example.test/","https://example.test/sub/","https://user@example.test/","https://example.test/?key=value"))
            refuses(() -> { try (SignedClient client=new SignedClient(URI.create(bad),"vck_"+"a".repeat(24),secret)) { client.scope(); } },"Origin format enforced");
    }
    private static void topologyScenarios() {
        check(Topology.authenticated(true,false,false,null),"Online-mode Paper is authenticated");
        check(Topology.authenticated(false,true,true,"a-long-forwarding-secret"),"Velocity modern forwarding with online proxy is authenticated");
        check(!Topology.authenticated(false,false,false,null),"Offline mode refused");
        check(!Topology.authenticated(false,true,false,"a-long-forwarding-secret"),"Offline-mode proxy refused");
        check(!Topology.authenticated(false,true,true,"short"),"Missing or weak forwarding secret refused");
        java.util.UUID bedrock=new java.util.UUID(0L,2535400000000000L);
        check(Topology.bedrock(bedrock) && "2535400000000000".equals(Topology.xuid(bedrock)),"Floodgate UUID maps to its XUID");
        java.util.UUID online=java.util.UUID.fromString("069a79f4-44e9-4726-a5be-fca90e38aaf5");
        check(!Topology.bedrock(online) && Topology.xuid(online) == null,"Online Java UUID is not Bedrock");
        check(!Topology.bedrock(new java.util.UUID(0L,0L)),"Nil UUID is not a Bedrock identity");
    }
    private static void policyScenarios() throws Exception {
        CommandPolicy policy=new CommandPolicy(List.of("lp","minecraft:give"));
        check(policy.allows("lp user BuilderQA parent add vip"),"Approved root allowed");
        check(policy.allows("MINECRAFT:give BuilderQA stone"),"Root case matches console behavior");
        check(!policy.allows("lpx grant") && !policy.allows("give BuilderQA stone"),"No prefix or implicit namespace permission");
        check(!policy.allows("/lp grant") && !policy.allows("lp grant\nstop"),"No slash or multiline root confusion");
        refuses(() -> new CommandPolicy(List.of()),"Local approval required");
        refuses(() -> new CommandPolicy(List.of("*")),"Wildcard approval refused");
    }
}
