<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Latest value per server, player and statistic, reported by that server's signed bridge.
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_server_id')->constrained()->cascadeOnDelete();
            $table->char('player_uuid', 36);
            $table->string('player_name', 32);
            $table->string('stat', 32);
            $table->bigInteger('value');
            $table->timestamp('updated_at')->nullable();

            $table->unique(['game_server_id', 'player_uuid', 'stat']);
            $table->index(['game_server_id', 'stat', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
