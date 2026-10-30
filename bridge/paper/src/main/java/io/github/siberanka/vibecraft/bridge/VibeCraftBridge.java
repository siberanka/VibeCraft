// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import java.io.IOException;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.UUID;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.Semaphore;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;
import java.util.function.Supplier;
import org.bukkit.Bukkit;
import org.bukkit.command.Command;
import org.bukkit.command.CommandSender;
import org.bukkit.entity.Player;
import org.bukkit.plugin.java.JavaPlugin;
import static io.github.siberanka.vibecraft.bridge.Protocol.Outcome;

/** Standalone, authenticated Paper Java servers; proxy/Folia/Bedrock require separate adapters. */
public final class VibeCraftBridge extends JavaPlugin {
    private final AtomicReference<ExecutionGate> activeGate=new AtomicReference<>();
    private final Semaphore linkSlots=new Semaphore(8);
    private final Map<UUID,Long> linkCooldown=new HashMap<>(); // Main thread only.
    private volatile boolean running;
    private volatile boolean halted;
    private ScheduledExecutorService worker;
    private SignedClient client;
    private Journal journal;
    private DeliveryProcessor processor;
    private CommandPolicy commandPolicy;
    private long lastWarning;
    private long lastRejection;
    private record Presence(List<String> online, int maxPlayers) {}
    private record StatRow(String uuid, String name, long playMinutes, long kills, long deaths, long mobKills) {}
    private long lastStats;
    private long lastBans;

    @Override public void onEnable() {
        saveDefaultConfig();
        if (!getConfig().getBoolean("enabled",false)) {
            getLogger().info("Bridge disabled. Configure the HTTPS site and per-server credentials before enabling."); return;
        }
        if (!authenticatedTopology() || isFolia()) {
            getLogger().severe("This adapter requires online-mode Paper or Velocity modern forwarding with an online-mode proxy. Offline mode, legacy BungeeCord forwarding and Folia are not supported.");
            getServer().getPluginManager().disablePlugin(this); return;
        }
        try {
            commandPolicy=new CommandPolicy(getConfig().getStringList("allowed-command-roots"));
            client=new SignedClient(URI.create(getConfig().getString("site","")),
                    getConfig().getString("key-id",""),getConfig().getString("secret",""));
            journal=new Journal(getDataFolder().toPath().resolve("receipts.vcj"), client.scope());
            processor=new DeliveryProcessor(journal,new PaperExecutor());
            worker=Executors.newSingleThreadScheduledExecutor(task -> {
                Thread thread=new Thread(task,"VibeCraft-Bridge"); thread.setDaemon(true); return thread;
            });
            Objects.requireNonNull(getCommand("vclink")).setExecutor(this);
            running=true;
            worker.schedule(this::poll,1,TimeUnit.SECONDS);
            getLogger().info("Bridge started with durable delivery history. Preserve receipts.vcj across updates and key rotation.");
        } catch (IOException | IllegalArgumentException ex) {
            getLogger().severe("Bridge configuration or durable history could not be opened. Processing remains disabled; reconcile before retrying.");
            getServer().getPluginManager().disablePlugin(this);
        }
    }
    private boolean authenticatedTopology() {
        org.bukkit.configuration.file.YamlConfiguration proxy=new org.bukkit.configuration.file.YamlConfiguration();
        try {
            java.io.File paperGlobal=new java.io.File(getServer().getWorldContainer(),"config/paper-global.yml");
            if (paperGlobal.isFile()) proxy=org.bukkit.configuration.file.YamlConfiguration.loadConfiguration(paperGlobal);
        } catch (RuntimeException ex) {
            // Unreadable proxy settings never widen trust: only online mode remains acceptable.
        }
        return Topology.authenticated(getServer().getOnlineMode(),proxy.getBoolean("proxies.velocity.enabled",false),
                proxy.getBoolean("proxies.velocity.online-mode",false),proxy.getString("proxies.velocity.secret",""));
    }
    private static boolean isFolia() {
        try { Class.forName("io.papermc.paper.threadedregions.RegionizedServer",false,VibeCraftBridge.class.getClassLoader()); return true; }
        catch (ClassNotFoundException ex) { return false; }
    }
    private <T> T onMain(Supplier<T> task) throws Exception {
        if (!running) throw new InterruptedException("Bridge stopping");
        Future<T> future=getServer().getScheduler().callSyncMethod(this,() -> {
            if (!running) throw new IllegalStateException("Bridge stopping");
            return task.get();
        });
        try { return future.get(2,TimeUnit.SECONDS); }
        finally { if (!future.isDone()) future.cancel(false); }
    }
    private Presence presence() throws Exception {
        return onMain(() -> new Presence(getServer().getOnlinePlayers().stream()
                .limit(5000).map(player -> player.getUniqueId().toString()).toList(),getServer().getMaxPlayers()));
    }
    private void poll() {
        if (!running || halted) return;
        int after=30;
        try {
            Presence snapshot=presence();
            JsonObject request=new JsonObject(); JsonArray online=new JsonArray(); snapshot.online().forEach(online::add);
            request.add("online",online); request.addProperty("players",snapshot.online().size());
            request.addProperty("max_players",snapshot.maxPlayers()); request.addProperty("platform","Paper");
            request.addProperty("plugin_version",Protocol.VERSION);
            Protocol.Batch batch=Protocol.batch(client.post("/api/bridge/v1/poll",bytes(request)));
            after=batch.pollAfter();
            long deadline=System.nanoTime()+TimeUnit.SECONDS.toNanos(batch.leaseSeconds()-12L);
            for (Protocol.Job job:batch.jobs()) {
                if (!running || System.nanoTime() >= deadline) break;
                Outcome outcome;
                try { outcome=processor.process(job); }
                catch (IOException ex) {
                    halted=true;
                    getLogger().severe("Delivery processing stopped. Check command approval, observed player identity and durable history before restarting.");
                    break;
                }
                if (!running || System.nanoTime() >= deadline) break;
                if (outcome == Outcome.REJECTED) {
                    long now=System.nanoTime();
                    if (now-lastRejection > TimeUnit.SECONDS.toNanos(60)) {
                        lastRejection=now; getLogger().warning("A delivery was refused before execution (command approval or observed player identity) and reported to the website for review.");
                    }
                }
                JsonObject response=Protocol.object(client.post("/api/bridge/v1/ack",Protocol.ack(job,outcome)));
                if (!response.has("accepted") || !response.get("accepted").isJsonArray())
                    throw new IOException("Invalid acknowledgement response");
                // A rejected receipt is never permission to rerun a command. The journal is authoritative locally.
            }
            shareStatistics();
            sharePunishments();
        } catch (InterruptedException ex) { Thread.currentThread().interrupt(); return; }
        catch (Exception ex) {
            after=60;
            long now=System.nanoTime();
            if (now-lastWarning > TimeUnit.SECONDS.toNanos(60)) {
                lastWarning=now; getLogger().warning("Bridge request or game snapshot failed; processing will retry without replaying recorded commands.");
            }
        } finally {
            if (running && !halted && worker != null && !worker.isShutdown())
                worker.schedule(this::poll,after,TimeUnit.SECONDS);
        }
    }
    /** Opt-in: reports four vanilla statistics of online players at most every five minutes. */
    private void shareStatistics() throws Exception {
        if (!getConfig().getBoolean("share-statistics",false)) return;
        long now=System.nanoTime();
        if (lastStats != 0 && now-lastStats < TimeUnit.MINUTES.toNanos(5)) return;
        lastStats=now;
        List<StatRow> rows=onMain(() -> getServer().getOnlinePlayers().stream().limit(500).map(player -> new StatRow(
                player.getUniqueId().toString(),player.getName(),
                player.getStatistic(org.bukkit.Statistic.PLAY_ONE_MINUTE)/1200L,
                player.getStatistic(org.bukkit.Statistic.PLAYER_KILLS),
                player.getStatistic(org.bukkit.Statistic.DEATHS),
                player.getStatistic(org.bukkit.Statistic.MOB_KILLS))).toList());
        if (rows.isEmpty()) return;
        JsonArray players=new JsonArray();
        for (StatRow row:rows) {
            JsonObject player=new JsonObject(); JsonObject stats=new JsonObject();
            player.addProperty("uuid",row.uuid()); player.addProperty("name",row.name());
            stats.addProperty("playtime_minutes",row.playMinutes()); stats.addProperty("kills",row.kills());
            stats.addProperty("deaths",row.deaths()); stats.addProperty("mob_kills",row.mobKills());
            player.add("stats",stats); players.add(player);
        }
        JsonObject request=new JsonObject(); request.add("players",players);
        Protocol.object(client.post("/api/bridge/v1/stats",bytes(request)));
    }
    /** Opt-in: reports this server's complete profile ban list at most every ten minutes. */
    private void sharePunishments() throws Exception {
        if (!getConfig().getBoolean("share-punishments",false)) return;
        long now=System.nanoTime();
        if (lastBans != 0 && now-lastBans < TimeUnit.MINUTES.toNanos(10)) return;
        lastBans=now;
        JsonArray bans=onMain(() -> {
            JsonArray rows=new JsonArray();
            org.bukkit.ban.ProfileBanList list=getServer().getBanList(io.papermc.paper.ban.BanListType.PROFILE);
            for (org.bukkit.BanEntry<?> entry:list.getEntries()) {
                if (rows.size() >= 5000) break;
                if (!(entry.getBanTarget() instanceof com.destroystokyo.paper.profile.PlayerProfile target)
                        || target.getId() == null || target.getName() == null) continue;
                JsonObject row=new JsonObject();
                row.addProperty("uuid",target.getId().toString()); row.addProperty("name",target.getName());
                if (entry.getReason() != null) row.addProperty("reason",entry.getReason());
                if (entry.getSource() != null) row.addProperty("staff",entry.getSource());
                if (entry.getCreated() != null) row.addProperty("issued_at",entry.getCreated().getTime()/1000L);
                if (entry.getExpiration() != null) row.addProperty("expires_at",entry.getExpiration().getTime()/1000L);
                rows.add(row);
            }
            return rows;
        });
        JsonObject request=new JsonObject(); request.add("bans",bans);
        Protocol.object(client.post("/api/bridge/v1/punishments",bytes(request)));
    }
    private final class PaperExecutor implements DeliveryProcessor.Executor {
        @Override public boolean ready(Protocol.Job job) throws Exception {
            if (!commandPolicy.allows(job.command())) throw new IOException("Command is not locally approved");
            return onMain(() -> readyPlayer(job));
        }
        @Override public Outcome execute(Protocol.Job job) throws Exception {
            ExecutionGate gate=new ExecutionGate(); activeGate.set(gate);
            try {
                if (!running) return Outcome.DEFERRED;
                getServer().getScheduler().runTask(VibeCraftBridge.this,() -> gate.run(() -> {
                    if (!running || !readyPlayer(job)) return Outcome.DEFERRED;
                    if (!Bukkit.isPrimaryThread()) return Outcome.UNCERTAIN;
                    return getServer().dispatchCommand(getServer().getConsoleSender(),job.command())
                            ? Outcome.DELIVERED : Outcome.UNCERTAIN;
                }));
                return gate.await(5000);
            } finally { gate.cancelPending(); activeGate.compareAndSet(gate,null); }
        }
        private boolean readyPlayer(Protocol.Job job) {
            Player observed=job.player()==null ? null : getServer().getPlayer(job.player());
            if (observed!=null && !job.matchesObserved(observed.getUniqueId(),observed.getName()))
                throw new IllegalStateException("Observed player identity no longer matches the delivery snapshot");
            return !job.requireOnline() || observed!=null;
        }
    }
    @Override public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (!(sender instanceof Player player)) { sender.sendPlainMessage("Run this command from your authenticated game account."); return true; }
        if (!running || halted || args.length != 1 || !args[0].matches("[A-Za-z0-9]{8}")) {
            player.sendPlainMessage("Use /vclink CODE with the eight-character code from your website account."); return true;
        }
        long now=System.nanoTime();
        linkCooldown.entrySet().removeIf(entry -> now-entry.getValue()>TimeUnit.SECONDS.toNanos(60));
        Long previous=linkCooldown.get(player.getUniqueId());
        if (previous != null && now-previous<TimeUnit.SECONDS.toNanos(30) || linkCooldown.size()>=5000 || !linkSlots.tryAcquire()) {
            player.sendPlainMessage("Please wait before trying another account link."); return true;
        }
        linkCooldown.put(player.getUniqueId(),now);
        UUID uuid=player.getUniqueId(); String name=player.getName(), code=args[0].toUpperCase(Locale.ROOT);
        worker.execute(() -> {
            boolean ok=false;
            try {
                if (!running) return;
                JsonObject request=new JsonObject(); request.addProperty("code",code);
                request.addProperty("uuid",uuid.toString()); request.addProperty("name",name);
                request.addProperty("edition",Topology.bedrock(uuid) ? "bedrock" : "java");
                if (Topology.bedrock(uuid)) request.addProperty("xuid",Topology.xuid(uuid));
                JsonObject response=Protocol.object(client.post("/api/bridge/v1/link",bytes(request)));
                ok=response.has("ok") && response.get("ok").isJsonPrimitive()
                        && response.get("ok").getAsJsonPrimitive().isBoolean() && response.get("ok").getAsBoolean();
            } catch (Exception ex) {
                if (ex instanceof InterruptedException) Thread.currentThread().interrupt();
            } finally {
                linkSlots.release();
                boolean linked=ok;
                if (running) getServer().getScheduler().runTask(this,() -> {
                    Player current=getServer().getPlayer(uuid);
                    if (current != null) current.sendPlainMessage(linked ? "Your website account is linked." : "The link could not be confirmed. Check your website account before retrying.");
                });
            }
        });
        player.sendPlainMessage("Checking your website link..."); return true;
    }
    private static byte[] bytes(JsonObject object) { return object.toString().getBytes(StandardCharsets.UTF_8); }
    @Override public void onDisable() {
        running=false;
        ExecutionGate gate=activeGate.get(); if (gate != null) gate.cancelPending();
        if (worker != null) worker.shutdownNow();
        if (client != null) client.close();
        if (journal != null) try { journal.close(); } catch (IOException ex) { getLogger().severe("Bridge journal could not be closed cleanly; reconcile pending deliveries."); }
    }
}
