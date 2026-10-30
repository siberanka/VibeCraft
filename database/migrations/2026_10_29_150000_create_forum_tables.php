<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 300)->nullable();
            $table->boolean('staff_only_topics')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('forum_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 120);
            $table->boolean('pinned')->default(false);
            $table->boolean('locked')->default(false);
            $table->boolean('hidden')->default(false);
            $table->unsignedInteger('replies')->default(0);
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();

            $table->index(['forum_category_id', 'hidden', 'pinned', 'last_post_at']);
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body_markdown');
            $table->text('body_html');
            $table->boolean('hidden')->default(false);
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['forum_thread_id', 'id']);
        });

        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate('forum.moderate', 'web');
            foreach (['admin', 'moderator'] as $roleName) {
                Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo('forum.moderate');
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('forum_categories');
    }
};
