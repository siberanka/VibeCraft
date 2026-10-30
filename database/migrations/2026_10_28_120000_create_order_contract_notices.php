<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_contract_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_contract_id')->unique()->constrained()->restrictOnDelete();
            $table->text('recipient');
            $table->char('message_key', 64)->unique();
            $table->string('state', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->char('attempt_token', 32)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error_code', 48)->nullable();
            $table->timestamps();
            $table->index(['state', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_contract_notices');
    }
};
