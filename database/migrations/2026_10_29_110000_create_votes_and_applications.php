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
        Schema::create('vote_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('url', 255);
            $table->unsignedSmallInteger('cooldown_hours')->default(24);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->text('questions');
            $table->boolean('is_open')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('staff_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('staff_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->string('status', 12)->default('pending')->index(); // pending|accepted|rejected
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'staff_position_id', 'status']);
        });

        // Existing installations receive the new review permission for staff roles.
        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate('applications.review', 'web');
            foreach (['admin', 'moderator'] as $roleName) {
                Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo('applications.review');
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_applications');
        Schema::dropIfExists('staff_positions');
        Schema::dropIfExists('vote_sites');
    }
};
