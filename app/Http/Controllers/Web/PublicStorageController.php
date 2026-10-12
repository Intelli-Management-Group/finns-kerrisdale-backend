<?php

namespace App\Http\Controllers\Web;

use Illuminate\Support\Facades\Storage;

class PublicStorageController
{
    public function show(string $path)
    {
        $segments = explode('/', $path);
        if (str_contains($path, '\\') || in_array('', $segments, true) || in_array('.', $segments, true) || in_array('..', $segments, true)) {
            abort(404);
        }

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path);
    }
}
