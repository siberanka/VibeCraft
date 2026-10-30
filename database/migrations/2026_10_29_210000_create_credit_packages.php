<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('bonus_minor')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'price_minor']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('bonus_minor')->default(0)->after('total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('bonus_minor');
        });
        Schema::dropIfExists('credit_packages');
    }
};
