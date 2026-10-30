<?php

use App\Domain\Content\Markdown;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-renders cached Markdown HTML so stored content follows the inert-image rule
 * (images only from uploaded media and bundled assets).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([['forum_posts', 'body_markdown', 'body_html'], ['posts', 'body_markdown', 'body_html'], ['pages', 'body_markdown', 'body_html'], ['products', 'description_markdown', 'description_html']] as [$table, $source, $target]) {
            DB::table($table)->select(['id', $source])->orderBy('id')->chunkById(200, function ($rows) use ($table, $source, $target) {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([$target => Markdown::render($row->{$source})]);
                }
            });
        }
    }

    public function down(): void
    {
        // Rendering is derived data; there is nothing to restore.
    }
};
