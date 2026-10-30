<?php

use App\Domain\Media\MediaLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('library_pack', 40)->nullable()->after('disk');
            $table->string('library_category', 40)->nullable()->after('library_pack');
            $table->index(['disk', 'library_pack', 'library_category']);
        });
        app(MediaLibrary::class)->sync();
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['disk', 'library_pack', 'library_category']);
            $table->dropColumn(['library_pack', 'library_category']);
        });
    }
};
