<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;

/**
 * Centralised image pipeline: validate → orient → resize (keep ratio) → compress → WebP → variants → DB row.
 */
class MediaService
{
    private ImageManager $images;

    public function __construct()
    {
        $this->images = config('media.driver') === 'imagick' ? ImageManager::imagick() : ImageManager::gd();
    }

    public function upload(UploadedFile $file, string $folder = 'general', ?int $userId = null, array $attrs = []): Media
    {
        return $this->storeFromPath($file->getRealPath(), $file->getClientOriginalName(), $folder, $userId, $attrs);
    }

    /**
     * Process any local image file (upload, import, remote download) into the media library.
     */
    public function storeFromPath(string $sourcePath, string $originalName, string $folder = 'general', ?int $userId = null, array $attrs = [], ?string $date = null): Media
    {
        $folder = $this->folder($folder);
        $processed = $this->process($sourcePath, $originalName);
        $disk = config('media.disk');
        $dir = $this->directory($folder, $date);
        $base = $this->uniqueBase($disk, $dir, $originalName);

        $paths = $this->write($disk, $dir, $base, $processed);

        return Media::create(array_merge([
            'disk' => $disk,
            'folder' => $folder,
            'path' => $paths['main'],
            'filename' => basename($paths['main']),
            'original_name' => mb_substr($originalName, 0, 250),
            'mime_type' => $processed['mime'],
            'extension' => $processed['ext'],
            'width' => $processed['width'],
            'height' => $processed['height'],
            'size_bytes' => strlen($processed['data']),
            'original_size_bytes' => @filesize($sourcePath) ?: null,
            'variants' => $paths['variants'],
            'uploaded_by' => $userId,
            'title' => Str::of(pathinfo($originalName, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->limit(120)->toString(),
        ], $attrs));
    }

    /**
     * Swap the file behind an existing media item; every page/post that references it keeps working.
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        $processed = $this->process($file->getRealPath(), $file->getClientOriginalName());
        $disk = $media->disk;
        $oldPath = $media->path;
        $oldUrl = $media->url;
        $oldVariants = $media->variants ?? [];

        $dir = dirname($oldPath);
        $base = pathinfo($oldPath, PATHINFO_FILENAME);
        if ($processed['ext'] !== $media->extension) {
            $base = $this->uniqueBase($disk, $dir, $file->getClientOriginalName());
        }

        $this->deleteFiles($disk, $oldPath, $oldVariants);
        $paths = $this->write($disk, $dir, $base, $processed);

        $media->fill([
            'path' => $paths['main'],
            'filename' => basename($paths['main']),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $processed['mime'],
            'extension' => $processed['ext'],
            'width' => $processed['width'],
            'height' => $processed['height'],
            'size_bytes' => strlen($processed['data']),
            'original_size_bytes' => $file->getSize(),
            'variants' => $paths['variants'],
        ])->save();

        if ($media->url !== $oldUrl) {
            $this->rewriteContentUrls($oldUrl, $media->url, $oldVariants, $media);
        }

        return $media->refresh();
    }

    public function delete(Media $media, bool $force = false): void
    {
        if ($force) {
            $this->deleteFiles($media->disk, $media->path, $media->variants ?? []);
            $media->forceDelete();
        } else {
            $media->delete();
        }
    }

    /**
     * @return array{data:string,mime:string,ext:string,width:int,height:int,variants:array<string,array{data:string,width:int,height:int}>}
     */
    public function process(string $sourcePath, string $originalName): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('The file is not a valid image.');
        }
        $srcMime = $info['mime'];
        if (! in_array($srcMime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            throw new RuntimeException('Unsupported image type: '.$srcMime);
        }

        // Animated GIFs are kept as they are (re-encoding would drop the animation).
        if ($srcMime === 'image/gif') {
            return [
                'data' => file_get_contents($sourcePath), 'mime' => 'image/gif', 'ext' => 'gif',
                'width' => $info[0], 'height' => $info[1], 'variants' => [],
            ];
        }

        $quality = max(40, min(100, (int) config('media.quality')));
        $toWebp = config('media.format') === 'webp';

        $image = $this->images->read($sourcePath);
        $image->scaleDown(width: config('media.max_width'), height: config('media.max_height'));

        [$data, $mime, $ext] = $this->encode($image, $srcMime, $toWebp, $quality);

        // If WebP/recompression came out larger than an already-small original, keep the original bytes.
        $originalBytes = filesize($sourcePath);
        if (! $toWebp && strlen($data) > $originalBytes && $image->width() === $info[0]) {
            $data = file_get_contents($sourcePath);
        }

        $variants = [];
        foreach (config('media.variants') as $name => $size) {
            if (max($image->width(), $image->height()) <= $size) {
                continue;
            }
            $v = $this->images->read($sourcePath);
            $v->scaleDown(width: $size, height: $size);
            [$vData] = $this->encode($v, $srcMime, $toWebp, $quality);
            $variants[$name] = ['data' => $vData, 'width' => $v->width(), 'height' => $v->height()];
        }

        return [
            'data' => $data, 'mime' => $mime, 'ext' => $ext,
            'width' => $image->width(), 'height' => $image->height(), 'variants' => $variants,
        ];
    }

    private function encode(ImageInterface $image, string $srcMime, bool $toWebp, int $quality): array
    {
        if ($toWebp) {
            return [(string) $image->toWebp(quality: $quality), 'image/webp', 'webp'];
        }

        return match ($srcMime) {
            'image/png' => [(string) $image->toPng(), 'image/png', 'png'],
            'image/webp' => [(string) $image->toWebp(quality: $quality), 'image/webp', 'webp'],
            default => [(string) $image->toJpeg(quality: $quality, progressive: true), 'image/jpeg', 'jpg'],
        };
    }

    /** @return array{main:string,variants:array<string,string>} */
    private function write(string $disk, string $dir, string $base, array $processed): array
    {
        $storage = Storage::disk($disk);
        $main = "$dir/$base.{$processed['ext']}";
        $storage->put($main, $processed['data'], 'public');

        $variants = [];
        foreach ($processed['variants'] as $name => $v) {
            $path = "$dir/$base-$name.{$processed['ext']}";
            $storage->put($path, $v['data'], 'public');
            $variants[$name] = $path;
        }

        return ['main' => $main, 'variants' => $variants];
    }

    private function deleteFiles(string $disk, string $path, array $variants): void
    {
        Storage::disk($disk)->delete(array_values(array_merge([$path], $variants)));
    }

    private function folder(string $folder): string
    {
        return in_array($folder, config('media.folders'), true) ? $folder : 'general';
    }

    private function directory(string $folder, ?string $date = null): string
    {
        $ts = $date ? strtotime($date) : time();

        return config('media.base_path')."/$folder/".date('Y', $ts).'/'.date('m', $ts);
    }

    private function uniqueBase(string $disk, string $dir, string $originalName): string
    {
        $name = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'image';
        $name = Str::limit($name, 60, '');
        do {
            $base = $name.'-'.Str::lower(Str::random(6));
        } while (Storage::disk($disk)->exists("$dir/$base.webp") || Storage::disk($disk)->exists("$dir/$base.jpg"));

        return $base;
    }

    private function rewriteContentUrls(string $oldUrl, string $newUrl, array $oldVariants, Media $media): void
    {
        $map = [$oldUrl => $newUrl];
        $newVariants = $media->variantUrls();
        foreach ($oldVariants as $name => $path) {
            $map[Media::urlFor($media->disk, $path)] = $newVariants[$name] ?? $newUrl;
        }
        DB::transaction(function () use ($map) {
            foreach ([Post::class, Page::class] as $model) {
                $model::withTrashed()->where(function ($q) use ($map) {
                    foreach (array_keys($map) as $old) {
                        $q->orWhere('content', 'like', '%'.$old.'%');
                    }
                })->each(function ($row) use ($map) {
                    $row->content = strtr($row->content, $map);
                    $row->saveQuietly();
                });
            }
        });
    }
}
