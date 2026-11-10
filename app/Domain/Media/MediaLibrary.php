<?php

namespace App\Domain\Media;

use App\Core\Settings\Settings;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\WikiArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Bundled icon packs plus the site's own uploads. Bundled icons are rows with
 * disk "library" so every image field keeps one foreign key; they are never
 * deleted. An upload can only be deleted when nothing refers to it.
 */
class MediaLibrary
{
    public const SETTINGS = ['site.logo_media_id' => 'Site logo', 'site.hero_media_id' => 'Home hero image'];

    private ?array $libraryIds = null;

    private ?array $picker = null;

    /** @return array{format: int, packs: list<array{id: string, name: string, categories: list<array{id: string, name: string, icons: list<array{slug: string, name: string, path: string}>}>}>} */
    public static function manifest(): array
    {
        $manifest = json_decode((string) file_get_contents(resource_path('media-library.json')), true, 16, JSON_THROW_ON_ERROR);

        return is_array($manifest) && ($manifest['format'] ?? null) === 1 ? $manifest : ['format' => 1, 'packs' => []];
    }

    public static function libraryFile(string $path): ?string
    {
        if (preg_match('~^library/[a-z0-9-]{1,40}/[a-z0-9-]{1,60}\.svg$~', $path) !== 1) {
            return null;
        }
        $file = public_path('assets/'.$path);

        return is_file($file) ? $file : null;
    }

    /** Insert or refresh the bundled icon rows; safe to run on every install and upgrade. */
    public function sync(): int
    {
        $rows = [];
        $now = now();
        foreach (self::manifest()['packs'] as $pack) {
            foreach ($pack['categories'] as $category) {
                foreach ($category['icons'] as $icon) {
                    $file = self::libraryFile($icon['path']);
                    if ($file === null) {
                        continue;
                    }
                    $rows[] = ['uploaded_by' => null, 'disk' => 'library', 'library_pack' => $pack['id'], 'library_category' => $category['id'],
                        'path' => $icon['path'], 'original_name' => basename($icon['path']), 'mime' => 'image/svg+xml', 'size' => (int) filesize($file),
                        'width' => 128, 'height' => 128, 'alt' => mb_substr($icon['name'], 0, 255), 'sha256' => hash_file('sha256', $file),
                        'created_at' => $now, 'updated_at' => $now];
                }
            }
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            Media::query()->upsert($chunk, ['path'], ['disk', 'library_pack', 'library_category', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'sha256', 'updated_at']);
        }
        $this->libraryIds = null;
        $this->picker = null;

        return count($rows);
    }

    public function isLibrary(?int $id): bool
    {
        if ($id === null) {
            return false;
        }
        $this->libraryIds ??= array_flip(Media::query()->where('disk', 'library')->pluck('id')->all());

        return isset($this->libraryIds[$id]);
    }

    /** @return array{uploads: Collection<int, Media>, packs: list<array{name: string, categories: list<array{id: string, name: string, icons: Collection<int, Media>}>}>} */
    public function picker(): array
    {
        if ($this->picker !== null) {
            return $this->picker;
        }
        $library = Media::query()->where('disk', 'library')->get(['id', 'path', 'alt', 'sha256', 'library_pack', 'library_category'])->keyBy('path');
        $packs = [];
        foreach (self::manifest()['packs'] as $pack) {
            $categories = [];
            foreach ($pack['categories'] as $category) {
                $icons = collect($category['icons'])->map(fn ($icon) => $library->get($icon['path']))->filter()->values();
                if ($icons->isNotEmpty()) {
                    $categories[] = ['id' => $pack['id'].'-'.$category['id'], 'name' => $category['name'], 'icons' => $icons];
                }
            }
            if ($categories !== []) {
                $packs[] = ['name' => $pack['name'], 'categories' => $categories];
            }
        }

        return $this->picker = ['uploads' => Media::query()->where('disk', 'local')->latest('id')->limit(200)->get(), 'packs' => $packs];
    }

    /**
     * Every place an image is used: image fields, site settings and Markdown
     * bodies that link to it.
     *
     * @return list<string>
     */
    public function usages(Media $media): array
    {
        $found = [];
        foreach ([[Product::class, 'image_media_id', 'Product'], [Category::class, 'image_media_id', 'Category'], [Post::class, 'cover_media_id', 'News post']] as [$model, $column, $label]) {
            foreach ($model::query()->where($column, $media->id)->limit(10)->get() as $record) {
                $found[] = __($label).': '.($record->getAttribute('name') ?? $record->getAttribute('title'));
            }
        }
        $settings = app(Settings::class);
        foreach (self::SETTINGS as $key => $label) {
            if ((int) $settings->get($key) === $media->id) {
                $found[] = __($label);
            }
        }
        foreach ((array) config('vibecraft.themes') as $theme) {
            if ((int) $settings->get("appearance.art.$theme") === $media->id) {
                $found[] = __('Home artwork').': '.ucfirst((string) $theme);
            }
        }
        $pattern = '~/media/'.$media->id.'(?![0-9])~';
        foreach ([[Product::class, 'description_markdown', 'Product'], [Post::class, 'body_markdown', 'News post'], [Page::class, 'body_markdown', 'Page'], [WikiArticle::class, 'body_markdown', 'Wiki page']] as [$model, $column, $label]) {
            $model::query()->where($column, 'like', '%/media/'.$media->id.'%')->limit(50)->get()
                ->filter(fn ($record) => preg_match($pattern, (string) $record->getAttribute($column)) === 1)
                ->each(function ($record) use (&$found, $label) {
                    $found[] = __($label).': '.($record->getAttribute('name') ?? $record->getAttribute('title'));
                });
        }

        return array_values(array_unique($found));
    }

    /**
     * Image-field and settings use counts for a page of uploads, in a fixed number of queries.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    public function useCounts(array $ids): array
    {
        $counts = array_fill_keys($ids, 0);
        foreach ([[Product::class, 'image_media_id'], [Category::class, 'image_media_id'], [Post::class, 'cover_media_id']] as [$model, $column]) {
            foreach ($model::query()->whereIn($column, $ids)->selectRaw($column.' as media_id, count(*) as uses')->groupBy($column)->get() as $row) {
                $counts[(int) $row->getAttribute('media_id')] += (int) $row->getAttribute('uses');
            }
        }
        $settings = app(Settings::class);
        $keys = array_merge(array_keys(self::SETTINGS), array_map(fn ($theme) => "appearance.art.$theme", (array) config('vibecraft.themes')));
        foreach ($keys as $key) {
            $id = (int) $settings->get($key);
            if (isset($counts[$id])) {
                $counts[$id]++;
            }
        }

        return $counts;
    }

    /** @return list<string> the places that still use it; empty when the upload was deleted */
    public function delete(Media $media): array
    {
        if ($media->disk !== 'local') {
            return [__('Bundled images cannot be deleted.')];
        }
        $usages = $this->usages($media);
        if ($usages !== []) {
            return $usages;
        }
        if (preg_match('~^media/[a-z0-9]{26}\.png$~', $media->path) === 1) {
            Storage::disk('local')->delete($media->path);
        }
        $media->delete();

        return [];
    }
}
