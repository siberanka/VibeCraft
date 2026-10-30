<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['wiki_categories', 'wiki_articles'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('reader_access', 16)->default('public');
                $table->json('reader_roles')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['wiki_articles', 'wiki_categories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['reader_access', 'reader_roles']));
        }
    }
};
