<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(false);
            $table->bigInteger('renew_price_minor')->nullable();
            $table->unsignedInteger('renewals')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('entitlements', function (Blueprint $table) {
            $table->dropColumn(['auto_renew', 'renew_price_minor', 'renewals']);
        });
    }
};
