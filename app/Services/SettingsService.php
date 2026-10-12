<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Media\ImageContentRewriter;
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
            $out[$row['key']] = $this->rewriteValue($row['value'], ImageContentRewriter::MODE_URL);
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
            ->map(fn ($items) => $items->map(fn ($s) => [
                'key' => $s->key,
                'value' => $this->rewriteValue($s->value, ImageContentRewriter::MODE_URL),
                'is_public' => $s->is_public,
            ])->values())
            ->toArray();
    }

    /** @param array<string,mixed> $values */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = Setting::query()->where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $this->rewriteValue($value, ImageContentRewriter::MODE_PATH)]);
            }
        }
        $this->flush();
    }

    public function set(string $key, mixed $value, string $group = 'general', bool $public = true): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $this->rewriteValue($value, ImageContentRewriter::MODE_PATH), 'group' => $group, 'is_public' => $public]
        );
        $this->flush();
    }

    private function rewriteValue(mixed $value, string $mode): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->rewriteValue($item, $mode);
            }

            return $value;
        }

        return is_string($value)
            ? app(ImageContentRewriter::class)->rewrite($value, $mode)
            : $value;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
