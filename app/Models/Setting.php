<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'is_public'];

    protected function casts(): array
    {
        return ['value' => 'json', 'is_public' => 'boolean'];
    }
}
