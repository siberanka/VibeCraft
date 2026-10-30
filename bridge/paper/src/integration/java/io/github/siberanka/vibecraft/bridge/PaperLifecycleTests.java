// SPDX-License-Identifier: LicenseRef-VibeCraft
// Copyright (c) 2026 siberanka. VibeCraft License; see LICENSE and NOTICE in the repository root.
package io.github.siberanka.vibecraft.bridge;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpsConfigurator;
import com.sun.net.httpserver.HttpsServer;
import java.io.IOException;
import java.net.InetAddress;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.security.KeyStore;
import java.time.Duration;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.UUID;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicBoolean;
import java.util.concurrent.atomic.AtomicInteger;
import java.util.concurrent.atomic.AtomicReference;
import java.util.function.BooleanSupplier;
import java.util.logging.Handler;
import java.util.logging.LogRecord;
import javax.net.ssl.KeyManagerFactory;
import javax.net.ssl.SSLContext;
import org.bukkit.Bukkit;
import org.bukkit.command.Command;
import org.bukkit.command.CommandSender;
import org.bukkit.configuration.file.YamlConfiguration;
import org.bukkit.plugin.java.JavaPlugin;
import org.mockbukkit.mockbukkit.MockBukkit;
import org.mockbukkit.mockbukkit.ServerMock;
import org.mockbukkit.mockbukkit.entity.PlayerMock;

/** Optional offline lifecycle suite. MockBukkit is a test dependency, never bundled.
 * No Minecraft server/world, EULA acceptance, remote origin or real credentials.
 */
public final class PaperLifecycleTests {
    private static final UUID PLAYER=UUID.fromString("12345678-1234-4234-8234-123456789abc");
    private static final String KEY="vck_"+"a".repeat(24), SECRET="b".repeat(64);
    private static final String JOB="01j"+"0".repeat(22)+"1", OFFLINE="01j"+"0".repeat(22)+"2";
    private static final String RENAMED="01j"+"0".repeat(22)+"3";
    private static int assertions;
    private PaperLifecycleTests() {}
    private static void check(boolean result,String label) { assertions++; if (!result) throw new AssertionError(label); }
    private static YamlConfiguration config(boolean enabled) {
        YamlConfiguration result=new YamlConfiguration();
        result.set("enabled",enabled);result.set("site","https://localhost:9443/");
        result.set("key-id",KEY);result.set("secret",SECRET);result.set("allowed-command-roots",List.of("fixturegrant"));
        return result;
    }
    private static JavaPlugin load(ServerMock server,YamlConfiguration configuration) {
        JavaPlugin plugin=(JavaPlugin)MockBukkit.loadJar("target/VibeCraftBridge-0.1.0-dev.jar");
        // MockBukkit's loadJar registers commands but omits the manifest permissions.
        // Register their actual defaults just as Paper's normal loader does.
        plugin.getPluginMeta().getPermissions().forEach(permission -> server.getPluginManager().addPermission(permission));
        for(String key:configuration.getKeys(false))plugin.getConfig().set(key,configuration.get(key));
        plugin.saveConfig();server.getPluginManager().enablePlugin(plugin);return plugin;
    }
    private static void until(ServerMock server,BooleanSupplier done,Duration budget) throws Exception {
        long deadline=System.nanoTime()+budget.toNanos();
        while (!done.getAsBoolean() && System.nanoTime()<deadline) {
            server.getScheduler().performTicks(1);Thread.sleep(20);
        }
        check(done.getAsBoolean(),"Lifecycle scenario deadline");
    }
    public static void main(String[] args) throws Exception {
        if (args.length!=1 || !Path.of(args[0]).toAbsolutePath().startsWith("/scratch/qa/"))
            throw new IllegalArgumentException("Provide isolated fixture PKCS12");
        try (Fixture fixture=new Fixture(Path.of(args[0]))) {
            configurationScenarios();
            deliveryScenarios(fixture);
            renamedScenario(fixture);
            System.out.println("PAPER_MOCK_LIFECYCLE_PASS assertions="+assertions+" load_enable_disable=verified main_thread_dispatch=verified lost_ack_reenable=verified online_identity=verified account_link=verified runtime=MockBukkit26.3.200");
        } finally { if (MockBukkit.isMocked()) MockBukkit.unmock(); }
    }
    private static void configurationScenarios() throws Exception {
        ServerMock server=MockBukkit.mock();server.setOnlineMode(true);
        JavaPlugin disabled=load(server,config(false));
        check(disabled.isEnabled(),"Disabled-by-default feature does not fail plugin loading");
        check(!Files.exists(disabled.getDataFolder().toPath().resolve("receipts.vcj")),"Default config creates no journal/worker");
        check("vibecraft.link".equals(disabled.getCommand("vclink").getPermission()),"Actual plugin manifest command permission loaded");
        MockBukkit.unmock();

        server=MockBukkit.mock();server.setOnlineMode(false);
        JavaPlugin offline=load(server,config(true));
        check(!offline.isEnabled(),"Offline-mode server refused by actual plugin lifecycle");
        MockBukkit.unmock();

        server=MockBukkit.mock();server.setOnlineMode(true);
        YamlConfiguration missing=config(true);missing.set("allowed-command-roots",List.of());
        JavaPlugin noApproval=load(server,missing);
        check(!noApproval.isEnabled(),"Empty command approval refuses enable");
        MockBukkit.unmock();

        server=MockBukkit.mock();server.setOnlineMode(true);
        YamlConfiguration insecure=config(true);insecure.set("site","http://localhost:9443/");
        JavaPlugin http=load(server,insecure);
        check(!http.isEnabled(),"HTTP origin refuses enable");
        MockBukkit.unmock();
    }
    private static void deliveryScenarios(Fixture fixture) throws Exception {
        fixture.reset("delivery");
        ServerMock server=MockBukkit.mock();server.setOnlineMode(true);
        PlayerMock player=new PlayerMock(server,"BuilderQA",PLAYER);server.addPlayer(player);
        AtomicInteger grants=new AtomicInteger();AtomicBoolean offMain=new AtomicBoolean();
        server.getCommandMap().register("fixture",new Command("fixturegrant") {
            @Override public boolean execute(CommandSender sender,String label,String[] values) {
                offMain.set(!Bukkit.isPrimaryThread());grants.incrementAndGet();return true;
            }
        });
        JavaPlugin plugin=load(server,config(true));
        player.recalculatePermissions();
        check(player.hasPermission("vibecraft.link"),"Actual manifest grants the ordinary player link permission");
        check(plugin.isEnabled(),"Configured online-mode plugin starts");
        until(server,()->fixture.lostAck.get()>0,Duration.ofSeconds(12));
        check(grants.get()==1 && !offMain.get(),"Actual Bukkit command dispatch executes once on main thread");
        check(fixture.observedOnline.get(),"Poll carries actual online player UUID");
        Path journal=plugin.getDataFolder().toPath().resolve("receipts.vcj");
        check(Files.size(journal)>37,"Plugin lifecycle creates durable delivery outcome");
        server.getPluginManager().disablePlugin(plugin);
        until(server,()->Thread.getAllStackTraces().keySet().stream().noneMatch(t->t.isAlive()&&t.getName().equals("VibeCraft-Bridge")),Duration.ofSeconds(3));
        try (Journal opened=new Journal(journal,"https://localhost:9443/")) {
            check(opened.previous(job(JOB,"a".repeat(64),"BuilderQA"))==Protocol.Outcome.DELIVERED,"Disable releases lock and preserves outcome");
        }
        fixture.reclaimed.set(true);server.getPluginManager().enablePlugin(plugin);
        until(server,fixture.delivered::get,Duration.ofSeconds(12));
        check(grants.get()==1,"Re-enable after lost ACK never redispatches recorded grant");
        check("b".repeat(64).equals(fixture.acceptedReceipt.get()),"Actual adapter acknowledges reclaimed lease receipt");

        check(server.dispatchCommand(player,"vclink ABCD2345"),"Player account command registered in actual command map");
        until(server,()->fixture.links.get()==1,Duration.ofSeconds(8));
        check(fixture.linkObserved.get(),"Link derives UUID/name/edition from authenticated Player");
        server.dispatchCommand(player,"vclink ABCD2345");
        server.getScheduler().performTicks(3);Thread.sleep(100);
        check(fixture.links.get()==1,"Player link cooldown prevents immediate repeated request");
        server.dispatchCommand(player,"vclink ABCD2345 22345678-1234-4234-8234-123456789abc");
        check(fixture.links.get()==1,"Player cannot supply an alternative UUID argument");

        fixture.mode.set("offline");
        until(server,()-> {
            if (fixture.disconnectRequested.getAndSet(false)) { player.disconnect();fixture.disconnected.countDown(); }
            return fixture.deferred.get();
        },Duration.ofSeconds(12));
        check(grants.get()==1,"Player leaving after poll cannot receive an online-only command");
        check(!player.isOnline(),"Offline fixture left through real mock player API");
        server.getPluginManager().disablePlugin(plugin);
        try (Journal opened=new Journal(journal,"https://localhost:9443/")) {
            check(opened.previous(job(OFFLINE,"c".repeat(64),"BuilderQA"))==null,"Offline precheck creates no execution intent");
        }
        MockBukkit.unmock();
    }
    private static void renamedScenario(Fixture fixture) throws Exception {
        fixture.reset("renamed");ServerMock server=MockBukkit.mock();server.setOnlineMode(true);
        server.addPlayer(new PlayerMock(server,"BuilderQA",PLAYER));
        AtomicInteger grants=new AtomicInteger();
        server.getCommandMap().register("fixture",new Command("fixturegrant") {
            @Override public boolean execute(CommandSender sender,String label,String[] values) { grants.incrementAndGet();return true; }
        });
        JavaPlugin plugin=load(server,config(true));
        until(server,()->fixture.rejected.get()>=2,Duration.ofSeconds(12));
        check(grants.get()==0,"Mismatched online name never receives the grant");
        check(fixture.rejected.get()>=2,"Refused delivery is reported as failed and polling continues");
        server.getPluginManager().disablePlugin(plugin);
        try (Journal journal=new Journal(plugin.getDataFolder().toPath().resolve("receipts.vcj"),"https://localhost:9443/")) {
            check(journal.previous(job(RENAMED,"d".repeat(64),"RenamedQA"))==null,"Identity mismatch leaves no execution intent");
        }
        MockBukkit.unmock();
    }
    private static Protocol.Job job(String id,String receipt,String name) {
        return new Protocol.Job(id,receipt,"fixturegrant BuilderQA",PLAYER,name,true);
    }
    private static final class Fixture implements AutoCloseable {
        private final HttpsServer server;
        private final java.util.concurrent.ExecutorService pool=Executors.newFixedThreadPool(2);
        private final Set<String> nonces=new HashSet<>();
        final AtomicReference<String> mode=new AtomicReference<>("delivery"),acceptedReceipt=new AtomicReference<>();
        final AtomicBoolean reclaimed=new AtomicBoolean(),delivered=new AtomicBoolean(),observedOnline=new AtomicBoolean(),
                linkObserved=new AtomicBoolean(),disconnectRequested=new AtomicBoolean(),deferred=new AtomicBoolean();
        final AtomicInteger lostAck=new AtomicInteger(),links=new AtomicInteger(),acks=new AtomicInteger(),rejected=new AtomicInteger();
        CountDownLatch disconnected=new CountDownLatch(1);
        Fixture(Path keyPath) throws Exception {
            KeyStore store=KeyStore.getInstance("PKCS12");
            try(var input=Files.newInputStream(keyPath)){store.load(input,"fixture-only-password".toCharArray());}
            KeyManagerFactory keys=KeyManagerFactory.getInstance(KeyManagerFactory.getDefaultAlgorithm());
            keys.init(store,"fixture-only-password".toCharArray());SSLContext tls=SSLContext.getInstance("TLS");tls.init(keys.getKeyManagers(),null,null);
            server=HttpsServer.create(new InetSocketAddress(InetAddress.getByName("127.0.0.1"),9443),4);
            server.setHttpsConfigurator(new HttpsConfigurator(tls));server.setExecutor(pool);
            server.createContext("/api/bridge/v1/",exchange->{try{handle(exchange);}catch(Exception ex){exchange.close();}});server.start();
        }
        void reset(String value) {
            mode.set(value);reclaimed.set(false);delivered.set(false);observedOnline.set(false);linkObserved.set(false);
            disconnectRequested.set(false);deferred.set(false);lostAck.set(0);links.set(0);acks.set(0);rejected.set(0);acceptedReceipt.set(null);
            disconnected=new CountDownLatch(1);
        }
        private void handle(HttpExchange exchange) throws Exception {
            byte[] body=exchange.getRequestBody().readNBytes(Protocol.MAX_BODY+1);
            String nonce=exchange.getRequestHeaders().getFirst("X-VC-Nonce"),timestamp=exchange.getRequestHeaders().getFirst("X-VC-Timestamp");
            if(body.length>Protocol.MAX_BODY||!"POST".equals(exchange.getRequestMethod())||!KEY.equals(exchange.getRequestHeaders().getFirst("X-VC-Key"))||nonce==null||timestamp==null)
                throw new IOException("Invalid fixture request");
            String expected=Protocol.sign(SECRET,"POST\n"+exchange.getRequestURI().getRawPath()+"\n"+timestamp+"\n"+nonce+"\n"+Protocol.hex(Protocol.hash(body)));
            synchronized(nonces){if(!nonces.add(nonce))throw new IOException("Fixture nonce replay");}
            if(!expected.equals(exchange.getRequestHeaders().getFirst("X-VC-Signature")))throw new IOException("Fixture request signature mismatch");
            JsonObject request=Protocol.object(body),response=new JsonObject();
            switch(exchange.getRequestURI().getPath()) {
                case "/api/bridge/v1/poll" -> {
                    if(request.getAsJsonArray("online").toString().contains(PLAYER.toString()))observedOnline.set(true);
                    JsonArray jobs=new JsonArray();Protocol.Job next=null;
                    if(mode.get().equals("renamed"))next=job(RENAMED,"d".repeat(64),"RenamedQA");
                    else if(mode.get().equals("offline")&&!deferred.get()) {
                        disconnectRequested.set(true);
                        if(!disconnected.await(2,TimeUnit.SECONDS))throw new IOException("Player disconnect fixture deadline");
                        next=job(OFFLINE,"c".repeat(64),"BuilderQA");
                    } else if(!delivered.get())next=job(JOB,(reclaimed.get()?"b":"a").repeat(64),"BuilderQA");
                    if(next!=null){JsonObject item=new JsonObject();item.addProperty("id",next.id());item.addProperty("lease_token",next.receipt());
                        item.addProperty("command",next.command());item.addProperty("player_uuid",PLAYER.toString());item.addProperty("player_name",next.name());item.addProperty("require_online",true);jobs.add(item);}
                    response.add("deliveries",jobs);response.addProperty("lease_seconds",120);response.addProperty("poll_after",1);
                }
                case "/api/bridge/v1/ack" -> {
                    JsonObject result=request.getAsJsonArray("results").get(0).getAsJsonObject();acks.incrementAndGet();
                    String id=Protocol.string(result,"id"),status=Protocol.string(result,"status"),receipt=Protocol.string(result,"lease_token");
                    if(JOB.equals(id)&&!reclaimed.get()){lostAck.incrementAndGet();exchange.sendResponseHeaders(503,-1);exchange.close();return;}
                    if(JOB.equals(id)&&reclaimed.get()&&"b".repeat(64).equals(receipt)&&status.equals("delivered")){acceptedReceipt.set(receipt);delivered.set(true);}
                    if(OFFLINE.equals(id)&&"deferred".equals(status))deferred.set(true);
                    if(RENAMED.equals(id)&&"failed".equals(status))rejected.incrementAndGet();
                    JsonArray accepted=new JsonArray();accepted.add(id);response.add("accepted",accepted);
                }
                case "/api/bridge/v1/link" -> {
                    links.incrementAndGet();linkObserved.set(PLAYER.toString().equals(Protocol.string(request,"uuid"))&&"BuilderQA".equals(Protocol.string(request,"name"))&&"java".equals(Protocol.string(request,"edition")));
                    response.addProperty("ok",true);
                }
                default -> throw new IOException("Unknown fixture endpoint");
            }
            byte[] raw=response.toString().getBytes(StandardCharsets.UTF_8);String now=Long.toString(System.currentTimeMillis()/1000);
            exchange.getResponseHeaders().set("Content-Type","application/json");exchange.getResponseHeaders().set("X-VC-Timestamp",now);
            exchange.getResponseHeaders().set("X-VC-Signature",Protocol.sign(SECRET,"response\n"+now+"\n"+nonce+"\n"+Protocol.hex(Protocol.hash(raw))));
            exchange.sendResponseHeaders(200,raw.length);exchange.getResponseBody().write(raw);exchange.close();
        }
        @Override public void close(){server.stop(0);pool.shutdownNow();}
    }
}
