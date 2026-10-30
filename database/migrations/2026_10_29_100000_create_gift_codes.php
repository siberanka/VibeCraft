<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Codes are shown to staff once at creation; only a SHA-256 digest is stored.
        Schema::create('gift_codes', function (Blueprint $table) {
            $table->id();
            $table->char('code_hash', 64)->unique();
            $table->string('code_hint', 4);
            $table->bigInteger('amount_minor');
            $table->unsignedInteger('max_redemptions')->default(1);
            $table->unsignedInteger('redemptions')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('gift_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamps();

            $table->unique(['gift_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_redemptions');
        Schema::dropIfExists('gift_codes');
    }
};
