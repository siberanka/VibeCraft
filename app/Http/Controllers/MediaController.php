<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Domain\Media\MediaLibrary;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function index(MediaLibrary $library)
    {
        $records = Media::query()->where('disk', 'local')->latest('id')->paginate(20);

        return view('theme::admin.media', ['records' => $records, 'useCounts' => $library->useCounts($records->pluck('id')->all()),
            'bundledCount' => Media::query()->where('disk', 'library')->count()]);
    }

    public function destroy(Request $request, Media $media, MediaLibrary $library, AuditLogger $audit)
    {
        $usages = $library->delete($media);
        if ($usages !== []) {
            throw ValidationException::withMessages(['media' => $media->disk === 'local'
                ? __('This image is still used. Change these first: :places', ['places' => implode(', ', $usages)])
                : $usages[0]]);
        }
        $audit->log('media.deleted', null, ['media' => $media->id], $request->user()->id);

        return back()->with('status', __('Image deleted.'));
    }

    public function show(Media $media)
    {
        if ($media->disk === 'library') {
            $file = MediaLibrary::libraryFile($media->path);
            abort_unless($file !== null, 404);

            return response()->file($file, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox", 'Cache-Control' => 'public, max-age=86400']);
        }
        abort_unless($media->disk === 'local' && preg_match('~^media/[a-z0-9]{26}\.png$~', $media->path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), ['Content-Type' => 'image/png', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $request->validate(['image' => ['required', 'file', 'max:4096', 'mimetypes:image/jpeg,image/png,image/webp'], 'alt' => ['nullable', 'string', 'max:255']]);
        $upload = $request->file('image');
        $size = @getimagesize($upload->getRealPath());
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] > 4096 || $size[1] > 4096 || $size[0] * $size[1] > 4_000_000) {
            throw ValidationException::withMessages(['image' => __('Image dimensions are too large or invalid.')]);
        }
        $image = @imagecreatefromstring(file_get_contents($upload->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['image' => __('Invalid image.')]);
        }
        $path = 'media/'.strtolower((string) Str::ulid()).'.png';
        ob_start();
        try {
            imagepng($image);
            $bytes = (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        Storage::disk('local')->put($path, $bytes);
        $media = Media::create(['uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path,
            'original_name' => 'image.png', 'mime' => 'image/png', 'size' => strlen($bytes), 'width' => $size[0], 'height' => $size[1],
            'alt' => $request->input('alt'), 'sha256' => hash('sha256', $bytes)]);
        $audit->log('media.uploaded', $media, [], $request->user()->id);

        return back()->with('status', __('Image saved.'))->with('media_id', $media->id);
    }
}
