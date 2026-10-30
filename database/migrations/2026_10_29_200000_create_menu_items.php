<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 10);
            $table->string('label', 40);
            $table->string('url', 255);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->boolean('new_tab')->default(false);
            $table->timestamps();

            $table->index(['location', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
