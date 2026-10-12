<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    public const TEMPLATES = ['default', 'home', 'about', 'designer', 'blog', 'contact'];
    public const STATUSES = ['draft', 'published'];

    protected $fillable = [
        'title', 'slug', 'template', 'content', 'sections', 'featured_media_id', 'status', 'published_at',
        'menu_order', 'seo_title', 'seo_description', 'og_media_id', 'canonical_url', 'noindex', 'author_id', 'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'published_at' => 'datetime',
            'noindex' => 'boolean',
            'menu_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')->where(fn ($w) => $w->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function ogMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'og_media_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Public path; the home page lives at "/". */
    public function path(): string
    {
        return $this->template === 'home' ? '/' : '/'.$this->slug.'/';
    }
}
