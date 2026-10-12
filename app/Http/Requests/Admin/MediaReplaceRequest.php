<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaReplaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = config('media.max_upload_kb');
        $mimes = implode(',', config('media.allowed_mimes'));

        return ['file' => ['required', 'file', 'image', "mimes:$mimes", 'mimetypes:image/jpeg,image/png,image/webp,image/gif', "max:$max"]];
    }
}
