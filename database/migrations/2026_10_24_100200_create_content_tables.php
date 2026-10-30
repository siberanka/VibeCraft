<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('category', 60)->nullable();
            $table->string('excerpt', 400)->nullable();
            $table->longText('body_markdown');
            $table->longText('body_html');
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('status', 12)->default('draft');
            $table->boolean('pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->longText('body_markdown');
            $table->longText('body_html');
            $table->string('meta_description', 300)->nullable();
            $table->string('status', 12)->default('draft');
            $table->boolean('show_in_nav')->default(false);
            $table->unsignedInteger('nav_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('posts');
    }
};
