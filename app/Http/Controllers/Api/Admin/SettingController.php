<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->settings->grouped(), 'Settings retrieved successfully');
    }

    public function update(Request $request): JsonResponse
    {
        $known = Setting::pluck('key')->all();
        $data = $request->validate(['settings' => ['required', 'array']])['settings'];
        $values = array_intersect_key($data, array_flip($known));

        // light per-key validation
        $rules = [];
        foreach ($values as $key => $v) {
            $rules["settings.$key"] = match (true) {
                str_ends_with($key, '_email'), $key === 'email' => ['nullable', 'email', 'max:255'],
                str_ends_with($key, '_url') => ['nullable', 'string', 'max:2000'],
                is_array($v) => ['nullable', 'array'],
                is_bool($v) => ['boolean'],
                default => ['nullable', 'max:5000'],
            };
        }
        $request->validate($rules);

        $this->settings->update($values);
        ContentCache::bust();

        return ApiResponse::success($this->settings->grouped(), 'Settings saved successfully');
    }
}
