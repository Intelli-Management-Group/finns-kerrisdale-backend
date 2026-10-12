<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $fillable = ['location', 'label', 'url', 'sort_order', 'open_in_new_tab', 'is_active'];

    protected function casts(): array
    {
        return ['open_in_new_tab' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
