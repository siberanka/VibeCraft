<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wiki_structure_locks', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('wiki_structure_locks')->insert(['id' => 1]);
        Schema::table('wiki_articles', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('wiki_articles')->restrictOnDelete();
            $table->index(['parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::table('wiki_articles', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id', 'sort']);
            $table->dropColumn('parent_id');
        });
        Schema::dropIfExists('wiki_structure_locks');
    }
};
