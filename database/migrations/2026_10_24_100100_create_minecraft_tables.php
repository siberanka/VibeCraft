<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->string('description', 255)->nullable();
            // Public join addresses (what players type) — not necessarily the address we ping.
            $table->string('java_address', 255)->nullable();
            $table->string('bedrock_address', 255)->nullable();
            $table->unsignedInteger('bedrock_port')->nullable();
            // Address used by the status pinger.
            $table->string('query_host', 255)->nullable();
            $table->unsignedInteger('query_port')->default(25565);
            $table->string('query_edition', 10)->default('java');
            $table->boolean('show_on_site')->default(true);
            $table->unsignedInteger('sort')->default(0);

            // Bridge credentials: public key id + encrypted HMAC secret (rotatable).
            $table->string('bridge_key_id', 40)->unique();
            $table->text('bridge_secret');
            $table->boolean('bridge_enabled')->default(true);
            $table->timestamp('bridge_last_seen_at')->nullable();
            $table->string('bridge_plugin_version', 40)->nullable();
            $table->string('bridge_platform', 80)->nullable();

            // Cached status snapshot (refreshed by the scheduler, never per page view).
            $table->boolean('status_online')->default(false);
            $table->unsignedInteger('status_players')->nullable();
            $table->unsignedInteger('status_max_players')->nullable();
            $table->string('status_version', 120)->nullable();
            $table->string('status_motd', 512)->nullable();
            $table->unsignedInteger('status_latency_ms')->nullable();
            $table->timestamp('status_checked_at')->nullable();
            $table->string('status_error', 255)->nullable();
            $table->timestamps();
        });

        // Replay protection for signed bridge requests.
        Schema::create('bridge_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_server_id')->constrained()->cascadeOnDelete();
            $table->string('nonce', 64);
            $table->timestamp('created_at')->index();

            $table->unique(['game_server_id', 'nonce']);
        });

        // Verified Minecraft identities. A name is never treated as proof of ownership.
        Schema::create('game_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('edition', 10); // java | bedrock
            $table->char('uuid', 36); // Java UUID or Floodgate UUID
            $table->string('xuid', 32)->nullable();
            $table->string('username', 32);
            $table->string('verified_via', 30);
            $table->foreignId('verified_on_server_id')->nullable()->constrained('game_servers')->nullOnDelete();
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->unique(['edition', 'uuid']);
            $table->unique(['user_id', 'edition']);
        });

        Schema::create('link_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_codes');
        Schema::dropIfExists('game_identities');
        Schema::dropIfExists('bridge_nonces');
        Schema::dropIfExists('game_servers');
    }
};
