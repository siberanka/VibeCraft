// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import com.google.gson.JsonObject;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpsConfigurator;
import com.sun.net.httpserver.HttpsServer;
import java.io.IOException;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.security.KeyStore;
import java.time.Clock;
import java.time.Duration;
import java.util.List;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicReference;
import javax.net.ssl.KeyManagerFactory;
import javax.net.ssl.SSLContext;
import javax.net.ssl.TrustManagerFactory;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** Optional isolated integration: loopback TLS fixture forwards to a real staging PHP API.
 * Never accepts a remote origin, real credentials or a trust-all TLS context.
 */
public final class EndpointTests {
    private enum Mode { REAL, REDIRECT, UNSIGNED, WRONG_SIGNATURE, OVERSIZE, INVALID_JSON, SLOW }
    private static int assertions;
    private EndpointTests() {}
    @FunctionalInterface private interface Checked { void run() throws Exception; }
    private static void check(boolean value,String message) { assertions++; if (!value) throw new AssertionError(message); }
    private static void refuses(Checked task,String message) throws Exception {
        boolean refused=false; try { task.run(); } catch (IOException ex) { refused=true; } check(refused,message);
    }
    public static void main(String[] args) throws Exception {
        if (args.length!=3) throw new IllegalArgumentException("Provide isolated fixture metadata, PKCS12 and PHP fixture path");
        Path infoPath=Path.of(args[0]), keyPath=Path.of(args[1]), phpPath=Path.of(args[2]);
        if (!infoPath.toAbsolutePath().startsWith("/scratch/qa/") || !keyPath.toAbsolutePath().startsWith("/scratch/qa/")
                || !phpPath.toAbsolutePath().startsWith("/media/qa/")) throw new IllegalArgumentException("Isolated fixture paths required");
        JsonObject info=Protocol.object(Files.readAllBytes(infoPath));
        String key=Protocol.string(info,"key"), secret=Protocol.string(info,"secret");
        KeyStore store=KeyStore.getInstance("PKCS12");
        try (var input=Files.newInputStream(keyPath)) { store.load(input,"fixture-only-password".toCharArray()); }
        KeyManagerFactory kmf=KeyManagerFactory.getInstance(KeyManagerFactory.getDefaultAlgorithm());
        kmf.init(store,"fixture-only-password".toCharArray());
        KeyStore trusted=KeyStore.getInstance(KeyStore.getDefaultType()); trusted.load(null,null);
        trusted.setCertificateEntry("fixture",store.getCertificate("fixture"));
        TrustManagerFactory tmf=TrustManagerFactory.getInstance(TrustManagerFactory.getDefaultAlgorithm()); tmf.init(trusted);
        SSLContext serverTls=SSLContext.getInstance("TLS"), clientTls=SSLContext.getInstance("TLS");
        serverTls.init(kmf.getKeyManagers(),null,null); clientTls.init(null,tmf.getTrustManagers(),null);
        HttpsServer server=HttpsServer.create(new InetSocketAddress(InetAddress.getByName("127.0.0.1"),9443),4);
        server.setHttpsConfigurator(new HttpsConfigurator(serverTls));
        var pool=Executors.newFixedThreadPool(2); server.setExecutor(pool);
        AtomicReference<Mode> mode=new AtomicReference<>(Mode.REAL); AtomicInteger followed=new AtomicInteger();
        try (HttpClient upstream=HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(3)).followRedirects(HttpClient.Redirect.NEVER).build()) {
            server.createContext("/api/bridge/v1/", exchange -> {
                try { handle(exchange,mode.get(),secret,upstream); }
                catch (Exception ex) { exchange.close(); }
            });
            server.createContext("/forbidden",exchange -> { followed.incrementAndGet(); exchange.sendResponseHeaders(204,-1); exchange.close(); });
            server.start();
            URI origin=URI.create("https://localhost:9443/");
            try (SignedClient client=new SignedClient(origin,key,secret,clientTls,Clock.systemUTC())) {
                byte[] poll="{\"online\":[],\"plugin_version\":\"fixture\",\"platform\":\"Paper\"}".getBytes(StandardCharsets.UTF_8);
                Protocol.Job first=Protocol.batch(client.post("/api/bridge/v1/poll",poll)).jobs().getFirst();
                AtomicInteger grants=new AtomicInteger();
                DeliveryProcessor.Executor game=new DeliveryProcessor.Executor() {
                    @Override public boolean ready(Protocol.Job job) { return true; }
                    @Override public Outcome execute(Protocol.Job job) { grants.incrementAndGet(); return Outcome.DELIVERED; }
                };
                Path history=infoPath.getParent().resolve("https-history.vcj");
                try (Journal journal=new Journal(history,client.scope())) {
                    check(new DeliveryProcessor(journal,game).process(first)==Outcome.DELIVERED,"Real poll grants once");
                }
                fixture(phpPath,"expire-lease");
                Protocol.Job reclaimed=Protocol.batch(client.post("/api/bridge/v1/poll",poll)).jobs().getFirst();
                check(reclaimed.id().equals(first.id()) && !reclaimed.receipt().equals(first.receipt()),"Real lease receipt rotated");
                JsonObject stale=Protocol.object(client.post("/api/bridge/v1/ack",Protocol.ack(first,Outcome.DELIVERED)));
                check(stale.getAsJsonArray("accepted").isEmpty(),"PHP rejects stale receipt");
                try (Journal journal=new Journal(history,client.scope())) {
                    check(new DeliveryProcessor(journal,game).process(reclaimed)==Outcome.DELIVERED && grants.get()==1,"Lost ACK plus restart never grants twice");
                }
                JsonObject settled=Protocol.object(client.post("/api/bridge/v1/ack",Protocol.ack(reclaimed,Outcome.DELIVERED)));
                check(settled.getAsJsonArray("accepted").size()==1,"PHP accepts current receipt"); fixture(phpPath,"verify-delivered");
                JsonObject duplicate=Protocol.object(client.post("/api/bridge/v1/ack",Protocol.ack(reclaimed,Outcome.DELIVERED)));
                check(duplicate.getAsJsonArray("accepted").size()==1 && grants.get()==1,"Duplicate signed ACK remains idempotent");
                fixture(phpPath,"seed-uncertain");
                Protocol.Job interrupted=Protocol.batch(client.post("/api/bridge/v1/poll",poll)).jobs().getFirst();
                try (Journal journal=new Journal(history,client.scope())) { journal.intent(interrupted); }
                try (Journal journal=new Journal(history,client.scope())) {
                    check(new DeliveryProcessor(journal,game).process(interrupted)==Outcome.UNCERTAIN && grants.get()==1,"Reopened intent cannot execute");
                }
                client.post("/api/bridge/v1/ack",Protocol.ack(interrupted,Outcome.UNCERTAIN)); fixture(phpPath,"verify-uncertain");
                check(true,"PHP retains manual review outcome");

                JsonObject link=new JsonObject(); link.addProperty("code",Protocol.string(info,"code"));
                link.addProperty("uuid","12345678-1234-4234-8234-123456789abc"); link.addProperty("name","BuilderQA"); link.addProperty("edition","java");
                byte[] linkBody=link.toString().getBytes(StandardCharsets.UTF_8);
                check(Protocol.object(client.post("/api/bridge/v1/link",linkBody)).get("ok").getAsBoolean(),"Signed identity link reaches PHP");
                refuses(() -> client.post("/api/bridge/v1/link",linkBody),"Single-use link code cannot replay"); fixture(phpPath,"verify-linked");

                mode.set(Mode.REDIRECT); refuses(() -> client.post("/api/bridge/v1/poll",poll),"Redirect refused");
                check(followed.get()==0,"Redirect target never contacted");
                mode.set(Mode.UNSIGNED); refuses(() -> client.post("/api/bridge/v1/poll",poll),"Unsigned response refused");
                mode.set(Mode.WRONG_SIGNATURE); refuses(() -> client.post("/api/bridge/v1/poll",poll),"Wrong response signature refused");
                mode.set(Mode.OVERSIZE); refuses(() -> client.post("/api/bridge/v1/poll",poll),"Streaming body cap enforced");
                mode.set(Mode.INVALID_JSON); refuses(() -> Protocol.batch(client.post("/api/bridge/v1/poll",poll)),"Signed malformed batch cannot execute");
                mode.set(Mode.SLOW); long start=System.nanoTime();
                refuses(() -> client.post("/api/bridge/v1/poll",poll),"Body delivery deadline enforced");
                check(System.nanoTime()-start<TimeUnit.SECONDS.toNanos(13),"Slow body bounded by total deadline");
                mode.set(Mode.REAL);
                try (SignedClient untrusted=new SignedClient(origin,key,secret)) {
                    refuses(() -> untrusted.post("/api/bridge/v1/poll",poll),"Production default refuses untrusted TLS certificate");
                }
                try (SignedClient wrongHost=new SignedClient(URI.create("https://127.0.0.1:9443/"),key,secret,clientTls,Clock.systemUTC())) {
                    refuses(() -> wrongHost.post("/api/bridge/v1/poll",poll),"Hostname validation remains active with fixture trust");
                }
                check(grants.get()==1,"All transport failures leave grant count unchanged");
                System.out.println("BRIDGE_HTTPS_PHP_PASS assertions="+assertions+" grants=1 lease_reclaim=verified restart=verified manual_review=verified link=verified tls=verified");
            }
        } finally { server.stop(0); pool.shutdownNow(); }
    }
    private static void fixture(Path script,String action) throws Exception {
        Process process=new ProcessBuilder("php84",script.toString(),action).redirectOutput(ProcessBuilder.Redirect.DISCARD)
                .redirectError(ProcessBuilder.Redirect.DISCARD).start();
        if (!process.waitFor(10,TimeUnit.SECONDS)) { process.destroyForcibly(); throw new IOException("Fixture command deadline"); }
        if (process.exitValue()!=0) throw new IOException("PHP fixture verification failed");
    }
    private static void handle(HttpExchange exchange,Mode mode,String secret,HttpClient upstream) throws Exception {
        byte[] request=exchange.getRequestBody().readNBytes(Protocol.MAX_BODY+1);
        if (request.length>Protocol.MAX_BODY) { exchange.sendResponseHeaders(413,-1); exchange.close(); return; }
        if (mode==Mode.REDIRECT) { exchange.getResponseHeaders().set("Location","/forbidden"); exchange.sendResponseHeaders(302,-1); exchange.close(); return; }
        byte[] response;
        if (mode==Mode.REAL) {
            HttpRequest.Builder builder=HttpRequest.newBuilder(URI.create("http://localhost:8080"+exchange.getRequestURI().getRawPath()))
                    .timeout(Duration.ofSeconds(5)).header("Content-Type","application/json").header("Accept","application/json");
            for (String key:List.of("X-VC-Key","X-VC-Timestamp","X-VC-Nonce","X-VC-Signature"))
                builder.header(key,exchange.getRequestHeaders().getFirst(key));
            HttpResponse<byte[]> backend=upstream.send(builder.POST(HttpRequest.BodyPublishers.ofByteArray(request)).build(),HttpResponse.BodyHandlers.ofByteArray());
            response=backend.body();
            for (String key:List.of("Content-Type","X-VC-Timestamp","X-VC-Signature"))
                backend.headers().firstValue(key).ifPresent(value -> exchange.getResponseHeaders().set(key,value));
            exchange.sendResponseHeaders(backend.statusCode(),response.length);
        } else {
            response=mode==Mode.OVERSIZE ? new byte[Protocol.MAX_BODY+1] : (mode==Mode.INVALID_JSON ? "{broken" : "{}").getBytes(StandardCharsets.UTF_8);
            exchange.getResponseHeaders().set("Content-Type","application/json");
            if (mode!=Mode.UNSIGNED) {
                String timestamp=Long.toString(System.currentTimeMillis()/1000);
                String nonce=exchange.getRequestHeaders().getFirst("X-VC-Nonce");
                exchange.getResponseHeaders().set("X-VC-Timestamp",timestamp);
                exchange.getResponseHeaders().set("X-VC-Signature",mode==Mode.WRONG_SIGNATURE ? "0".repeat(64)
                        : Protocol.sign(secret,"response\n"+timestamp+"\n"+nonce+"\n"+Protocol.hex(Protocol.hash(response))));
            }
            exchange.sendResponseHeaders(200,response.length);
            if (mode==Mode.SLOW) { exchange.getResponseBody().write(response,0,1); exchange.getResponseBody().flush(); Thread.sleep(12000); }
        }
        exchange.getResponseBody().write(response); exchange.close();
    }
}
