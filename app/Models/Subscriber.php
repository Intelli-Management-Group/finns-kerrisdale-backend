<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    protected $fillable = ['email', 'status', 'token', 'source', 'ip_address', 'confirmed_at', 'unsubscribed_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }
}
