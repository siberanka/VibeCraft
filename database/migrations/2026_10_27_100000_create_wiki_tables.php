<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_categories', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('description', 400)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'is_visible', 'sort']);
        });
        Schema::create('wiki_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wiki_category_id')->constrained('wiki_categories')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('locale', 5);
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->string('summary', 400)->nullable();
            $table->longText('body_markdown');
            $table->longText('body_html');
            $table->json('table_of_contents');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
            $table->index(['locale', 'status', 'published_at']);
            $table->index(['wiki_category_id', 'locale', 'status', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_articles');
        Schema::dropIfExists('wiki_categories');
    }
};
