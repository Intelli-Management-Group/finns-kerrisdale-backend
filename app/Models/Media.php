<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'disk', 'folder', 'path', 'filename', 'original_name', 'mime_type', 'extension',
        'width', 'height', 'size_bytes', 'original_size_bytes', 'alt', 'title', 'caption',
        'variants', 'uploaded_by', 'wp_id', 'wp_url',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'original_size_bytes' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return self::urlFor($this->disk, $this->path);
    }

    public static function urlFor(string $disk, string $path): string
    {
        $baseUrl = rtrim((string) config('media.base_url', ''), '/');
        if ($disk === 'public' && $baseUrl !== '') {
            return $baseUrl.'/storage/'.ltrim($path, '/');
        }

        return Storage::disk($disk)->url($path);
    }

    /** @return array<string,string> */
    public function variantUrls(): array
    {
        $out = [];
        foreach ($this->variants ?? [] as $name => $path) {
            $out[$name] = self::urlFor($this->disk, $path);
        }

        return $out;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Where this image is used, so the admin can't delete an image a page still shows.
     *
     * @return array<int, array{type:string,id:int,title:string}>
     */
    public function usages(): array
    {
        $uses = [];
        $needle = '%'.$this->path.'%';

        foreach (Post::withTrashed()->where('featured_media_id', $this->id)->orWhere('og_media_id', $this->id)->orWhere('content', 'like', $needle)->get(['id', 'title']) as $p) {
            $uses[] = ['type' => 'post', 'id' => $p->id, 'title' => $p->title ?: '(no title)'];
        }
        foreach (Page::withTrashed()->where('featured_media_id', $this->id)->orWhere('og_media_id', $this->id)->orWhere('content', 'like', $needle)->get(['id', 'title']) as $p) {
            $uses[] = ['type' => 'page', 'id' => $p->id, 'title' => $p->title];
        }
        foreach (Gallery::query()->where('cover_media_id', $this->id)->orWhereHas('media', fn ($q) => $q->where('media.id', $this->id))->get(['id', 'title']) as $g) {
            $uses[] = ['type' => 'gallery', 'id' => $g->id, 'title' => $g->title];
        }
        foreach (Brand::query()->where('logo_media_id', $this->id)->orWhere('brand_thumbnail_media_id', $this->id)->get(['id', 'name']) as $b) {
            $uses[] = ['type' => 'brand', 'id' => $b->id, 'title' => $b->name];
        }
        if (\DB::table('post_media')->where('media_id', $this->id)->exists()) {
            foreach (Post::whereHas('images', fn ($q) => $q->where('media.id', $this->id))->get(['id', 'title']) as $p) {
                $uses[] = ['type' => 'post', 'id' => $p->id, 'title' => $p->title ?: '(no title)'];
            }
        }

        return collect($uses)->unique(fn ($u) => $u['type'].$u['id'])->values()->all();
    }
}
