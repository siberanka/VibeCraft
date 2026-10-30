<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('slug', 60)->unique();
            $table->char('color', 7)->default('#3db7a6');
            $table->string('description', 160)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::create('badge_user', function (Blueprint $table) {
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['badge_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_user');
        Schema::dropIfExists('badges');
    }
};
