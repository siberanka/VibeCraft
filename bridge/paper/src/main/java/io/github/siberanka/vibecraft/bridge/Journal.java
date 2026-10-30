// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import java.io.EOFException;
import java.io.IOException;
import java.nio.ByteBuffer;
import java.nio.channels.FileChannel;
import java.nio.channels.FileLock;
import java.nio.channels.OverlappingFileLockException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.LinkOption;
import java.nio.file.Path;
import java.nio.file.StandardOpenOption;
import java.security.MessageDigest;
import java.util.HashMap;
import java.util.Map;
import java.util.zip.CRC32;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** Append-only, exclusive, fsynced receipt history. Torn history stops processing. */
public final class Journal implements AutoCloseable {
    private static final int HEADER = 37, PAYLOAD = 59, ROW = 63;
    private static final long MAX_BYTES = 32L * 1024 * 1024;
    private final FileChannel channel;
    private final FileLock lock;
    private final Map<String, Entry> entries = new HashMap<>();
    private boolean broken;
    private record Entry(byte[] fingerprint, Outcome outcome) {}

    public Journal(Path path, String origin) throws IOException {
        Path parent = path.toAbsolutePath().getParent();
        Files.createDirectories(parent);
        if (Files.isSymbolicLink(parent) || Files.isSymbolicLink(path)) throw new IOException("Journal links refused");
        channel = FileChannel.open(path, StandardOpenOption.CREATE, StandardOpenOption.READ,
                StandardOpenOption.WRITE, LinkOption.NOFOLLOW_LINKS);
        FileLock acquired = null;
        try {
            try { acquired = channel.tryLock(); }
            catch (OverlappingFileLockException ex) { throw new IOException("Journal already in use"); }
            if (acquired == null) throw new IOException("Journal already in use");
            lock = acquired;
            byte[] expected = ByteBuffer.allocate(HEADER).put("VCJ1\n".getBytes(StandardCharsets.US_ASCII))
                    .put(Protocol.hash(origin.getBytes(StandardCharsets.UTF_8))).array();
            if (channel.size() == 0) {
                write(ByteBuffer.wrap(expected)); channel.force(true);
                if (Files.getFileStore(parent).supportsFileAttributeView("posix"))
                    try (FileChannel directory = FileChannel.open(parent, StandardOpenOption.READ)) { directory.force(true); }
            }
            if (channel.size() < HEADER || channel.size() > MAX_BYTES || (channel.size()-HEADER)%ROW != 0)
                throw new IOException("Journal damaged or full; reconciliation required");
            channel.position(0);
            if (!MessageDigest.isEqual(read(HEADER).array(), expected)) throw new IOException("Journal site or format mismatch");
            while (channel.position() < channel.size()) {
                ByteBuffer row = read(ROW);
                CRC32 crc = new CRC32(); crc.update(row.array(),0,PAYLOAD);
                if (row.getInt(PAYLOAD) != (int) crc.getValue()) throw new IOException("Journal checksum mismatch");
                byte[] idBytes = new byte[26], fingerprint = new byte[32];
                row.get(idBytes).get(fingerprint);
                String id = new String(idBytes,StandardCharsets.US_ASCII);
                int state = Byte.toUnsignedInt(row.get());
                if (!id.matches("[0-7][0-9a-hjkmnp-tv-z]{25}") || state > Outcome.UNCERTAIN.ordinal())
                    throw new IOException("Journal record invalid");
                Entry previous = entries.get(id);
                Outcome outcome = Outcome.values()[state];
                if (previous != null && (!MessageDigest.isEqual(previous.fingerprint(),fingerprint)
                        || !(previous.outcome() == Outcome.INTENT && outcome != Outcome.INTENT
                        || previous.outcome() == Outcome.DEFERRED && outcome == Outcome.INTENT)))
                    throw new IOException("Journal transition invalid");
                if (previous == null && outcome != Outcome.INTENT) throw new IOException("Journal intent missing");
                entries.put(id,new Entry(fingerprint,outcome));
            }
        } catch (IOException | RuntimeException ex) {
            if (acquired != null) acquired.release();
            channel.close(); throw ex;
        }
    }
    public synchronized Outcome previous(Protocol.Job job) throws IOException {
        if (broken || !channel.isOpen()) throw new IOException("Journal unavailable");
        Entry value = entries.get(job.id());
        if (value == null) return null;
        if (!MessageDigest.isEqual(value.fingerprint(),job.fingerprint())) throw new IOException("Delivery content changed; reconciliation required");
        return value.outcome() == Outcome.INTENT ? Outcome.UNCERTAIN : value.outcome();
    }
    public synchronized void intent(Protocol.Job job) throws IOException {
        Entry before = entries.get(job.id());
        if (before != null && (before.outcome() != Outcome.DEFERRED
                || !MessageDigest.isEqual(before.fingerprint(),job.fingerprint())))
            throw new IOException("Delivery already recorded");
        append(job,Outcome.INTENT);
    }
    public synchronized void complete(Protocol.Job job, Outcome outcome) throws IOException {
        Entry before = entries.get(job.id());
        if (before == null || before.outcome() != Outcome.INTENT || outcome == Outcome.INTENT
                || !MessageDigest.isEqual(before.fingerprint(), job.fingerprint()))
            throw new IOException("Invalid durable outcome transition");
        append(job,outcome);
    }
    private void append(Protocol.Job job, Outcome outcome) throws IOException {
        if (broken || !channel.isOpen()) throw new IOException("Journal unavailable");
        // Reserve a second row before intent so a normal final outcome can be persisted.
        int reserve = outcome == Outcome.INTENT ? ROW*2 : ROW;
        if (channel.size() + reserve > MAX_BYTES) throw new IOException("Journal capacity reached; reconciliation required");
        ByteBuffer row = ByteBuffer.allocate(ROW).put(job.id().getBytes(StandardCharsets.US_ASCII))
                .put(job.fingerprint()).put((byte)outcome.ordinal());
        CRC32 crc = new CRC32(); crc.update(row.array(),0,PAYLOAD); row.putInt((int)crc.getValue()).flip();
        try { channel.position(channel.size()); write(row); channel.force(true); }
        catch (IOException ex) { broken = true; throw ex; }
        entries.put(job.id(),new Entry(job.fingerprint(),outcome));
    }
    private ByteBuffer read(int count) throws IOException {
        ByteBuffer value=ByteBuffer.allocate(count);
        while (value.hasRemaining()) if (channel.read(value)<0) throw new EOFException("Incomplete journal");
        return value.flip();
    }
    private void write(ByteBuffer value) throws IOException { while (value.hasRemaining()) channel.write(value); }
    @Override public synchronized void close() throws IOException { lock.release(); channel.close(); }
}
