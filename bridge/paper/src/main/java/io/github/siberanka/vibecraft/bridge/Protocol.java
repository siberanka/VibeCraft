// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.google.gson.Strictness;
import com.google.gson.stream.JsonReader;
import com.google.gson.stream.JsonToken;
import java.io.IOException;
import java.io.StringReader;
import java.nio.charset.StandardCharsets;
import java.nio.ByteBuffer;
import java.nio.charset.CodingErrorAction;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.HexFormat;
import java.util.List;
import java.util.UUID;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

/** Exact UTF-8 wire contract. A complete batch is validated before any execution. */
public final class Protocol {
    public static final String VERSION = "0.9.0-beta";
    public static final int MAX_BODY = 262144;
    private Protocol() {}

    /** REJECTED: refused before any command started; never journaled. Ordinals are persisted, append only. */
    public enum Outcome { INTENT, DELIVERED, DEFERRED, UNCERTAIN, REJECTED }
    public record Job(String id, String receipt, String command, UUID player, String name, boolean requireOnline) {
        public Job {
            if (id == null || !id.matches("[0-7][0-9a-hjkmnp-tv-z]{25}")
                    || receipt == null || !receipt.matches("[0-9a-f]{64}")
                    || command == null || command.isBlank() || command.length() > 2048
                    || command.startsWith("/") || command.codePoints().anyMatch(Character::isISOControl)
                    || name != null && !name.matches("[.*]?[A-Za-z0-9_]{2,16}")
                    || requireOnline && player == null) {
                throw new IllegalArgumentException("Invalid delivery fields");
            }
        }
        public byte[] fingerprint() {
            // Length prefixes prevent ambiguous concatenation; the lease receipt may rotate.
            String[] fields = {command, player == null ? "" : player.toString(), name == null ? "" : name,
                    Boolean.toString(requireOnline)};
            StringBuilder value = new StringBuilder();
            for (String field : fields) value.append(field.length()).append(':').append(field);
            return hash(value.toString().getBytes(StandardCharsets.UTF_8));
        }
        @Override public String toString() { return "Delivery[redacted]"; }
        public boolean matchesObserved(UUID observedUuid, String observedName) {
            return player != null && player.equals(observedUuid)
                    && (name == null || name.equalsIgnoreCase(observedName));
        }
    }
    public record Batch(List<Job> jobs, int leaseSeconds, int pollAfter) {
        public Batch { jobs = List.copyOf(jobs); }
    }

    public static Batch batch(byte[] raw) throws IOException {
        JsonObject root = object(raw);
        JsonElement entries = root.get("deliveries");
        if (entries == null || !entries.isJsonArray() || entries.getAsJsonArray().size() > 100)
            throw new IOException("Invalid delivery batch");
        int lease = integer(root, "lease_seconds", 15, 3600);
        int after = integer(root, "poll_after", 1, 300);
        List<Job> jobs = new ArrayList<>();
        HashSet<String> ids = new HashSet<>();
        try {
            for (JsonElement entry : entries.getAsJsonArray()) {
                if (!entry.isJsonObject()) throw new IOException("Invalid delivery");
                JsonObject item = entry.getAsJsonObject();
                String uuid = nullableString(item, "player_uuid");
                if (uuid != null && !uuid.matches("[0-9a-fA-F]{8}(-[0-9a-fA-F]{4}){3}-[0-9a-fA-F]{12}"))
                    throw new IOException("Invalid player identity");
                JsonElement online = item.get("require_online");
                if (online == null || !online.isJsonPrimitive() || !online.getAsJsonPrimitive().isBoolean())
                    throw new IOException("Invalid online policy");
                Job job = new Job(string(item,"id"), string(item,"lease_token"), string(item,"command"),
                        uuid == null ? null : UUID.fromString(uuid), nullableString(item,"player_name"), online.getAsBoolean());
                if (!ids.add(job.id())) throw new IOException("Duplicate delivery in batch");
                jobs.add(job);
            }
        } catch (IllegalArgumentException | IllegalStateException ex) {
            throw new IOException("Invalid delivery fields");
        }
        return new Batch(jobs, lease, after);
    }

    public static JsonObject object(byte[] raw) throws IOException {
        if (raw.length > MAX_BODY) throw new IOException("Response exceeds limit");
        String decoded=StandardCharsets.UTF_8.newDecoder().onMalformedInput(CodingErrorAction.REPORT)
                .onUnmappableCharacter(CodingErrorAction.REPORT).decode(ByteBuffer.wrap(raw)).toString();
        try (JsonReader reader = new JsonReader(new StringReader(decoded))) {
            reader.setStrictness(Strictness.STRICT);
            JsonElement parsed = JsonParser.parseReader(reader);
            if (!parsed.isJsonObject() || reader.peek() != JsonToken.END_DOCUMENT) throw new IOException("Expected JSON object");
            return parsed.getAsJsonObject();
        } catch (RuntimeException ex) {
            throw new IOException("Invalid JSON response");
        }
    }

    public static String string(JsonObject object, String key) throws IOException {
        JsonElement value = object.get(key);
        if (value == null || !value.isJsonPrimitive() || !value.getAsJsonPrimitive().isString())
            throw new IOException("Invalid response field");
        return value.getAsString();
    }
    private static String nullableString(JsonObject object, String key) throws IOException {
        if (!object.has(key)) throw new IOException("Missing response field");
        return object.get(key).isJsonNull() ? null : string(object, key);
    }
    private static int integer(JsonObject object, String key, int min, int max) throws IOException {
        JsonElement value = object.get(key);
        if (value == null || !value.isJsonPrimitive() || !value.getAsJsonPrimitive().isNumber()
                || !value.getAsString().matches("[0-9]{1,5}")) throw new IOException("Invalid response number");
        int number = value.getAsInt();
        if (number < min || number > max) throw new IOException("Response number out of range");
        return number;
    }

    public static byte[] ack(Job job, Outcome outcome) {
        if (outcome == Outcome.INTENT) throw new IllegalArgumentException("Unresolved outcome");
        JsonObject item = new JsonObject();
        item.addProperty("id", job.id()); item.addProperty("lease_token", job.receipt());
        if (outcome == Outcome.REJECTED) {
            item.addProperty("status", "failed");
            item.addProperty("message", "Refused by the game server before execution (command approval or player identity)");
        } else {
            item.addProperty("status", outcome.name().toLowerCase(java.util.Locale.ROOT));
        }
        JsonArray results = new JsonArray(); results.add(item);
        JsonObject root = new JsonObject(); root.add("results", results);
        return root.toString().getBytes(StandardCharsets.UTF_8);
    }
    public static byte[] hash(byte[] data) {
        try { return MessageDigest.getInstance("SHA-256").digest(data); }
        catch (NoSuchAlgorithmException ex) { throw new IllegalStateException("SHA-256 unavailable", ex); }
    }
    public static String hex(byte[] data) { return HexFormat.of().formatHex(data); }
    public static String sign(String secret, String canonical) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8),"HmacSHA256"));
            return hex(mac.doFinal(canonical.getBytes(StandardCharsets.UTF_8)));
        } catch (java.security.GeneralSecurityException ex) { throw new IllegalStateException("HMAC unavailable",ex); }
    }
}
