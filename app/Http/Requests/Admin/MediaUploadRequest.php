<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = config('media.max_upload_kb');
        $mimes = implode(',', config('media.allowed_mimes'));

        return [
            'files' => ['required_without:file', 'array', 'max:20'],
            'files.*' => ['file', 'image', "mimes:$mimes", 'mimetypes:image/jpeg,image/png,image/webp,image/gif', "max:$max"],
            'file' => ['required_without:files', 'file', 'image', "mimes:$mimes", 'mimetypes:image/jpeg,image/png,image/webp,image/gif', "max:$max"],
            'folder' => ['nullable', Rule::in(config('media.folders'))],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        $limit = ini_get('upload_max_filesize');
        $msg = "This file is larger than the server's upload limit ($limit). Raise upload_max_filesize and post_max_size in php.ini (see docs/INSTALLATION.md).";

        return ['files.*.uploaded' => $msg, 'file.uploaded' => $msg];
    }
}
