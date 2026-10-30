<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wiki_articles', fn (Blueprint $table) => $table->json('faqs')->nullable());
        Schema::table('wiki_revisions', fn (Blueprint $table) => $table->json('faqs')->nullable());
        Schema::create('wiki_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->boolean('helpful');
            $table->timestamps();
            $table->unique(['wiki_article_id', 'user_id', 'revision']);
            $table->index(['wiki_article_id', 'revision', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_feedback');
        Schema::table('wiki_revisions', fn (Blueprint $table) => $table->dropColumn('faqs'));
        Schema::table('wiki_articles', fn (Blueprint $table) => $table->dropColumn('faqs'));
    }
};
