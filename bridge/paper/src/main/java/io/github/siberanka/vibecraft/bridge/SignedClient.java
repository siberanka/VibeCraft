// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpHeaders;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.ByteBuffer;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.time.Clock;
import java.time.Duration;
import java.util.List;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.CompletionStage;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.Flow;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;
import javax.net.ssl.SSLContext;

/** HTTPS only, no redirects, bounded body and deadline, request-bound response HMAC. */
public final class SignedClient implements AutoCloseable {
    private final URI origin;
    private final String key, secret;
    private final HttpClient client;
    private final Clock clock;
    public SignedClient(URI origin, String key, String secret) { this(origin,key,secret,null,Clock.systemUTC()); }
    SignedClient(URI origin, String key, String secret, SSLContext fixtureTrust, Clock clock) {
        if (!"https".equals(origin.getScheme()) || origin.getHost() == null
                || origin.getUserInfo() != null || origin.getQuery() != null || origin.getFragment() != null
                || !(origin.getRawPath().isEmpty() || origin.getRawPath().equals("/"))
                || origin.getPort() == 0 || origin.getPort() > 65535
                || key == null || !key.matches("vck_[a-z0-9]{24}")
                || secret == null || !secret.matches("[0-9a-f]{64}"))
            throw new IllegalArgumentException("Invalid HTTPS origin or bridge credentials");
        this.origin=origin.resolve("/"); this.key=key; this.secret=secret; this.clock=clock;
        HttpClient.Builder builder=HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(5))
                .followRedirects(HttpClient.Redirect.NEVER).version(HttpClient.Version.HTTP_1_1);
        if (fixtureTrust != null) builder.sslContext(fixtureTrust);
        client=builder.build();
    }
    public String scope() { return origin.toASCIIString(); }
    public byte[] post(String path, byte[] body) throws IOException, InterruptedException {
        if (!path.matches("/api/bridge/v1/(poll|ack|link|stats|punishments)") || body.length > Protocol.MAX_BODY)
            throw new IllegalArgumentException("Invalid bridge request");
        String timestamp=Long.toString(clock.instant().getEpochSecond());
        String nonce=UUID.randomUUID().toString().replace("-", "");
        String signature=Protocol.sign(secret,"POST\n"+path+"\n"+timestamp+"\n"+nonce+"\n"+Protocol.hex(Protocol.hash(body)));
        HttpRequest request=HttpRequest.newBuilder(origin.resolve(path)).timeout(Duration.ofSeconds(8))
                .header("Content-Type","application/json").header("Accept","application/json")
                .header("User-Agent","VibeCraftBridge/"+Protocol.VERSION)
                .header("X-VC-Key",key).header("X-VC-Timestamp",timestamp)
                .header("X-VC-Nonce",nonce).header("X-VC-Signature",signature)
                .POST(HttpRequest.BodyPublishers.ofByteArray(body)).build();
        CompletableFuture<HttpResponse<byte[]>> future=client.sendAsync(request,info -> new BoundedBody());
        HttpResponse<byte[]> response;
        try { response=future.get(10, TimeUnit.SECONDS); }
        catch (TimeoutException ex) { future.cancel(true); throw new IOException("Bridge response deadline exceeded"); }
        catch (ExecutionException ex) { throw new IOException("Bridge transport failed"); }
        catch (InterruptedException ex) { future.cancel(true); throw ex; }
        if (response.statusCode() != 200) throw new IOException("Bridge returned HTTP "+response.statusCode());
        String type=single(response.headers(),"Content-Type");
        if (!type.matches("(?i)application/json(?:;\\s*charset=utf-8)?")) throw new IOException("Unexpected bridge content type");
        if (!response.headers().allValues("Content-Encoding").isEmpty()) throw new IOException("Encoded responses refused");
        verify(secret,nonce,response.headers(),response.body(),clock.instant().getEpochSecond());
        return response.body();
    }
    static void verify(String secret, String nonce, HttpHeaders headers, byte[] body, long now) throws IOException {
        String timestamp=single(headers,"X-VC-Timestamp"), signature=single(headers,"X-VC-Signature");
        if (!timestamp.matches("[0-9]{1,12}") || !signature.matches("[0-9a-f]{64}") || body.length > Protocol.MAX_BODY)
            throw new IOException("Invalid signed response");
        if (Math.abs(now-Long.parseLong(timestamp)) > 120) throw new IOException("Bridge response clock skew");
        String expected=Protocol.sign(secret,"response\n"+timestamp+"\n"+nonce+"\n"+Protocol.hex(Protocol.hash(body)));
        if (!MessageDigest.isEqual(expected.getBytes(StandardCharsets.US_ASCII),signature.getBytes(StandardCharsets.US_ASCII)))
            throw new IOException("Bridge response signature mismatch");
    }
    private static String single(HttpHeaders headers, String key) throws IOException {
        List<String> values=headers.allValues(key);
        if (values.size()!=1) throw new IOException("Missing or duplicate bridge header");
        return values.getFirst();
    }
    @Override public void close() { client.shutdownNow(); }

    private static final class BoundedBody implements HttpResponse.BodySubscriber<byte[]> {
        private final CompletableFuture<byte[]> result=new CompletableFuture<>();
        private final ByteArrayOutputStream bytes=new ByteArrayOutputStream();
        private Flow.Subscription subscription;
        @Override public CompletionStage<byte[]> getBody() { return result; }
        @Override public void onSubscribe(Flow.Subscription value) { subscription=value; value.request(1); }
        @Override public void onNext(List<ByteBuffer> items) {
            for (ByteBuffer item:items) {
                if ((long)bytes.size()+item.remaining()>Protocol.MAX_BODY) {
                    subscription.cancel(); result.completeExceptionally(new IOException("Bridge body exceeds limit")); return;
                }
                byte[] part=new byte[item.remaining()]; item.get(part); bytes.writeBytes(part);
            }
            subscription.request(1);
        }
        @Override public void onError(Throwable error) { result.completeExceptionally(error); }
        @Override public void onComplete() { result.complete(bytes.toByteArray()); }
    }
}
