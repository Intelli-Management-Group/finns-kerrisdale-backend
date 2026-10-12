<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'ip_address', 'user_agent', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
