<?php

namespace App\Models;

use App\Casts\ImageHtml;
use App\Support\Html;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'featured_media_id', 'author_id', 'author_name', 'status',
        'published_at', 'is_featured', 'seo_title', 'seo_description', 'og_media_id', 'canonical_url', 'noindex', 'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'content' => ImageHtml::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'noindex' => 'boolean',
            'views' => 'integer',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_media_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'post_media')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function path(): string
    {
        return '/'.$this->slug.'/';
    }

    /** Featured image URL, falling back to the first image in the content (how the WordPress theme behaves). */
    public function coverUrl(): ?string
    {
        if ($this->featuredMedia) {
            return $this->featuredMedia->url;
        }

        return Html::firstImage($this->content ?? '');
    }

    public function summary(int $length = 160): string
    {
        return $this->excerpt ?: Html::excerpt($this->content ?? '', $length);
    }

    public function displayTitle(): string
    {
        return $this->title !== '' ? $this->title : '';
    }
}
