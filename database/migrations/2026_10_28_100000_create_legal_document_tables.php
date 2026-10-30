<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_markets', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->string('subdivision', 8)->default('');
            $table->json('trader');
            $table->json('locales');
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['country', 'subdivision']);
        });
        Schema::create('legal_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_market_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->text('body_markdown');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('current_document_id')->nullable();
            $table->timestamps();
            $table->unique(['legal_market_id', 'kind', 'locale']);
        });
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_policy_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->char('country', 2);
            $table->string('subdivision', 8)->default('');
            $table->string('kind', 24);
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->text('body_markdown');
            $table->json('trader_snapshot');
            $table->char('document_sha256', 64);
            $table->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->unique(['legal_policy_id', 'version']);
        });
        Schema::table('legal_policies', function (Blueprint $table) {
            $table->foreign('current_document_id')->references('id')->on('legal_documents')->restrictOnDelete();
        });
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context', 24);
            $table->string('action', 16);
            $table->char('document_sha256', 64);
            $table->timestamp('recorded_at');
            $table->index(['user_id', 'context', 'recorded_at']);
            $table->index(['order_id', 'legal_document_id']);
            $table->unique(['order_id', 'legal_document_id', 'context', 'action'], 'legal_order_document_action_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::table('legal_policies', fn (Blueprint $table) => $table->dropForeign(['current_document_id']));
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('legal_policies');
        Schema::dropIfExists('legal_markets');
    }
};
