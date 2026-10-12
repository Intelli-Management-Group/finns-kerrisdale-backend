<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    /** @return array<string,mixed> */
    public function all(bool $publicOnly = false): array
    {
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->get(['key', 'value', 'group', 'is_public'])->toArray());

        $out = [];
        foreach ($rows as $row) {
            if ($publicOnly && ! $row['is_public']) {
                continue;
            }
            $out[$row['key']] = $row['value'];
        }

        return $out;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @return array<string,array<int,array{key:string,value:mixed,is_public:bool}>> */
    public function grouped(): array
    {
        return Setting::query()->orderBy('group')->orderBy('id')->get()
            ->groupBy('group')
            ->map(fn ($items) => $items->map(fn ($s) => ['key' => $s->key, 'value' => $s->value, 'is_public' => $s->is_public])->values())
            ->toArray();
    }

    /** @param array<string,mixed> $values */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = Setting::query()->where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $value]);
            }
        }
        $this->flush();
    }

    public function set(string $key, mixed $value, string $group = 'general', bool $public = true): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group, 'is_public' => $public]);
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
