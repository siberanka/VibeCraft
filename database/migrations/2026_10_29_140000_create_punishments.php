<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot of a game server's own ban list, replaced on every signed report.
        Schema::create('punishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_server_id')->constrained()->cascadeOnDelete();
            $table->char('player_uuid', 36);
            $table->string('player_name', 32);
            $table->string('type', 12)->default('ban');
            $table->string('reason', 255)->nullable();
            $table->string('staff', 32)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['game_server_id', 'player_uuid', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punishments');
    }
};
