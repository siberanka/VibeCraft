<?php

namespace Tests\Feature;

use App\Core\Settings\Settings;
use App\Domain\Media\MediaLibrary;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    private function admin()
    {
        app(Settings::class)->set('security.require_admin_2fa', false);

        return $this->actingAs($this->customer('admin'))->withSession(['auth.password_confirmed_at' => time()]);
    }

    private function upload(): Media
    {
        Storage::fake('local');
        $path = 'media/'.strtolower((string) Str::ulid()).'.png';
        Storage::disk('local')->put($path, 'fixture');

        return Media::query()->create(['disk' => 'local', 'path' => $path, 'original_name' => 'image.png', 'mime' => 'image/png',
            'size' => 7, 'width' => 64, 'height' => 64, 'alt' => 'Rank art', 'sha256' => hash('sha256', 'fixture')]);
    }

    public function test_bundled_icons_are_installed_and_served_as_inert_svg(): void
    {
        $count = collect(MediaLibrary::manifest()['packs'])->flatMap(fn ($pack) => $pack['categories'])->sum(fn ($category) => count($category['icons']));
        $this->assertGreaterThan(100, $count);
        $this->assertSame($count, Media::query()->where('disk', 'library')->count());
        $this->assertSame($count, app(MediaLibrary::class)->sync());

        $icon = Media::query()->where('path', 'library/swords/diamond-sword.svg')->sole();
        $response = $this->get(route('media.show', $icon))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('<script', (string) file_get_contents(public_path('assets/library/swords/diamond-sword.svg')));
    }

    public function test_product_form_offers_the_library_and_a_bundled_icon_can_be_chosen(): void
    {
        $icon = Media::query()->where('path', 'library/ranks/vip.svg')->sole();
        $product = $this->product();
        $this->admin()->get('/admin/products/'.$product->id.'/edit')->assertOk()->assertSee('value="'.$icon->id.'"', false)->assertSee(__('Rank tags'));

        $product->update(['image_media_id' => $icon->id]);
        $this->get(route('store.product', $product))->assertOk()->assertSee('media-icon', false);
    }

    public function test_unused_upload_is_deleted_and_bundled_icons_cannot_be(): void
    {
        $media = $this->upload();
        Storage::disk('local')->assertExists($media->path);
        $this->admin()->delete(route('admin.media.destroy', $media))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertModelMissing($media);
        Storage::disk('local')->assertMissing($media->path);

        $icon = Media::query()->where('disk', 'library')->firstOrFail();
        $this->admin()->delete(route('admin.media.destroy', $icon))->assertSessionHasErrors('media');
        $this->assertModelExists($icon);
    }

    public function test_upload_in_use_is_kept_and_the_places_are_listed(): void
    {
        $media = $this->upload();
        $product = $this->product(['image_media_id' => $media->id]);
        $this->admin()->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');
        $this->assertModelExists($media);
        $this->assertContains(__('Product').': '.$product->name, app(MediaLibrary::class)->usages($media));

        $product->update(['image_media_id' => null]);
        Post::query()->create(['title' => 'Patch notes', 'slug' => 'patch-notes', 'body_markdown' => '![Art](/media/'.$media->id.'?v=1)', 'body_html' => '', 'status' => 'published']);
        $this->assertSame([__('News post').': Patch notes'], app(MediaLibrary::class)->usages($media));
        $this->admin()->delete(route('admin.media.destroy', $media))->assertSessionHasErrors('media');

        app(Settings::class)->set('site.hero_media_id', $media->id);
        $this->assertContains(__('Home hero image'), app(MediaLibrary::class)->usages($media));
    }

    public function test_only_content_managers_delete_media(): void
    {
        $media = $this->upload();
        app(Settings::class)->set('security.require_admin_2fa', false);
        $this->actingAs($this->customer('support'))->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('admin.media.destroy', $media))->assertForbidden();
        $this->assertModelExists($media);
    }
}
