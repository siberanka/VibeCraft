<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('chest_delivery')->default(false);
        });
        // The chest choice is frozen with the order item, like its price and commands.
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('held_in_chest')->default(false);
            $table->timestamp('chest_claimed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['held_in_chest', 'chest_claimed_at']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('chest_delivery');
        });
    }
};
