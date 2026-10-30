<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_article_id')->constrained('wiki_articles')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->string('summary', 400)->nullable();
            $table->longText('body_markdown');
            $table->string('origin', 16)->default('edit');
            $table->unsignedInteger('based_on_revision')->nullable();
            $table->timestamp('created_at');
            $table->unique(['wiki_article_id', 'revision']);
        });
        // Only the current saved content is known; older edits are not invented.
        DB::table('wiki_articles')->select(['id', 'revision', 'locale', 'title', 'summary', 'body_markdown', 'updated_at'])
            ->orderBy('id')->chunkById(50, function (Collection $articles) {
                $rows = $articles->map(fn (object $article) => [
                    'wiki_article_id' => $article->id, 'revision' => $article->revision, 'editor_id' => null,
                    'locale' => $article->locale, 'title' => $article->title, 'summary' => $article->summary,
                    'body_markdown' => $article->body_markdown, 'origin' => 'baseline',
                    'based_on_revision' => null, 'created_at' => $article->updated_at ?? now(),
                ])->all();
                DB::table('wiki_revisions')->insert($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_revisions');
    }
};
