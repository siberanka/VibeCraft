<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->char('confirmation_sha256', 64)->unique();
            $table->string('context', 16);
            $table->char('review_sha256', 64);
            $table->json('snapshot');
            $table->longText('execution_plan');
            $table->char('execution_sha256', 64);
            $table->longText('receipt_text');
            $table->char('receipt_sha256', 64);
            $table->char('snapshot_sha256', 64);
            $table->timestamp('recorded_at');
        });
        Schema::create('order_contract_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_acceptance_id')->constrained()->restrictOnDelete();
            $table->foreignId('legal_document_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->char('document_sha256', 64);
            $table->unique(['order_contract_id', 'kind'], 'contract_document_kind_unique');
            $table->unique(['order_contract_id', 'legal_acceptance_id'], 'contract_document_acceptance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_contract_documents');
        Schema::dropIfExists('order_contracts');
    }
};
