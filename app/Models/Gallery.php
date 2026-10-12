<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gallery extends Model
{
    use SoftDeletes;

    protected $fillable = ['title', 'slug', 'description', 'cover_media_id', 'status', 'sort_order'];

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'gallery_media')
            ->withPivot(['caption', 'sort_order'])
            ->orderByPivot('sort_order');
    }
}
